package id.payoung.kasir

import android.Manifest
import android.annotation.SuppressLint
import android.app.Activity
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothManager
import android.bluetooth.BluetoothSocket
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import io.flutter.plugin.common.BinaryMessenger
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import java.io.IOException
import java.util.UUID
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

/**
 * Printer struk Bluetooth Classic (SPP) untuk aplikasi kasir (PRD §17.2.5, v1.80). Dipanggil dari Dart lewat kanal
 * `id.payoung.kasir/bluetooth-klasik`:
 * - `Status` → {Didukung, Aktif, Izin}
 * - `MintaIzin` → true bila izin "Perangkat di sekitar" (BLUETOOTH_CONNECT, Android 12+) diberikan
 * - `DaftarTerpasang` → [{Nama, Alamat}] printer yang sudah di-pair di pengaturan Bluetooth Android
 * - `Kirim` {Alamat, Data} → buka soket RFCOMM (UUID SPP), tulis semua byte, tunggu printer menerima, tutup.
 * Galat dikirim sebagai kode: BluetoothTidakAda, BluetoothMati, IzinDitolak, PrinterTidakDitemukan, GagalTersambung,
 * GagalMengirim. Soket dibuka per pekerjaan cetak agar printer bisa dipakai perangkat lain di sela transaksi.
 */
class KanalBluetoothKlasik(private val aktivitas: Activity, messenger: BinaryMessenger) : MethodChannel.MethodCallHandler {
    companion object {
        const val NAMA_KANAL = "id.payoung.kasir/bluetooth-klasik"
        private const val KODE_IZIN = 4201

        /** Serial Port Profile: layanan standar printer thermal Bluetooth Classic. */
        private val UUID_SPP: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
    }

    private val kanal = MethodChannel(messenger, NAMA_KANAL)
    private val pekerja: ExecutorService = Executors.newSingleThreadExecutor()
    private val utama = Handler(Looper.getMainLooper())
    private var hasilIzinTertunda: MethodChannel.Result? = null

    init {
        kanal.setMethodCallHandler(this)
    }

    fun Tutup() {
        kanal.setMethodCallHandler(null)
        pekerja.shutdown()
    }

    /** Jawaban galat selalu lewat utas utama dan paling banyak sekali per panggilan. */
    private fun KirimGalat(result: MethodChannel.Result, kode: String, pesan: String, rincian: String?) {
        utama.post {
            try {
                result.error(kode, pesan, rincian)
            } catch (_: IllegalStateException) {
                // Jawaban sudah terkirim.
            }
        }
    }

    private fun KirimBerhasil(result: MethodChannel.Result) {
        utama.post {
            try {
                result.success(null)
            } catch (_: IllegalStateException) {
                // Jawaban sudah terkirim.
            }
        }
    }

    private fun GalatKeKode(galat: Throwable): String = when (galat) {
        is SecurityException -> "IzinDitolak"
        is IOException -> "GagalMengirim"
        else -> "GalatTakTerduga"
    }

    private fun PesanGalat(galat: Throwable): String = when (galat) {
        is SecurityException -> "Izinkan akses Perangkat di sekitar agar printer Bluetooth bisa dipakai."
        is IOException -> "Struk gagal terkirim ke printer Bluetooth. Coba cetak lagi."
        else -> "Printer Bluetooth tidak bisa dipakai. Pastikan printer menyala dan sudah di-pair, lalu coba lagi."
    }

    private fun AmbilAdaptor(): BluetoothAdapter? =
        (aktivitas.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager)?.adapter

    private fun CekIzin(): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.S ||
            aktivitas.checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT) == PackageManager.PERMISSION_GRANTED

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        // Galat tak terduga (mis. SecurityException dari izin yang dicabut) jadi jawaban galat ke Dart, bukan aplikasi tertutup.
        try {
            TanganiPanggilan(call, result)
        } catch (galat: Throwable) {
            KirimGalat(result, GalatKeKode(galat), PesanGalat(galat), galat.message)
        }
    }

    private fun TanganiPanggilan(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "Status" -> {
                val adaptor = AmbilAdaptor()
                result.success(
                    mapOf(
                        "Didukung" to (adaptor != null),
                        "Aktif" to (adaptor?.isEnabled == true),
                        "Izin" to CekIzin(),
                    ),
                )
            }
            "MintaIzin" -> MintaIzin(result)
            "DaftarTerpasang" -> DaftarTerpasang(result)
            "Kirim" -> {
                val alamat = call.argument<String>("Alamat")
                val data = call.argument<ByteArray>("Data")
                if (alamat == null || data == null) {
                    result.error("ArgumenTidakValid", "Alamat dan data printer wajib diisi.", null)
                } else {
                    Kirim(alamat, data, result)
                }
            }
            else -> result.notImplemented()
        }
    }

    private fun MintaIzin(result: MethodChannel.Result) {
        if (CekIzin()) {
            result.success(true)
            return
        }
        if (hasilIzinTertunda != null) {
            result.error("IzinSedangDiminta", "Permintaan izin Bluetooth masih terbuka.", null)
            return
        }
        hasilIzinTertunda = result
        aktivitas.requestPermissions(arrayOf(Manifest.permission.BLUETOOTH_CONNECT), KODE_IZIN)
    }

    fun TanganiHasilIzin(kode: Int, hasil: IntArray) {
        if (kode != KODE_IZIN) {
            return
        }
        val tertunda = hasilIzinTertunda ?: return
        hasilIzinTertunda = null
        tertunda.success(hasil.isNotEmpty() && hasil.all { it == PackageManager.PERMISSION_GRANTED })
    }

    @SuppressLint("MissingPermission")
    private fun DaftarTerpasang(result: MethodChannel.Result) {
        val adaptor = AmbilAdaptor() ?: return result.error("BluetoothTidakAda", "Perangkat ini tidak punya Bluetooth.", null)
        if (!CekIzin()) {
            return result.error("IzinDitolak", "Izinkan akses Perangkat di sekitar agar printer Bluetooth bisa dipakai.", null)
        }
        if (!adaptor.isEnabled) {
            return result.error("BluetoothMati", "Bluetooth mati. Nyalakan Bluetooth, lalu coba lagi.", null)
        }
        val daftar = (adaptor.bondedDevices ?: emptySet())
            .filter { it.type != BluetoothDevice.DEVICE_TYPE_LE }
            .map { mapOf("Nama" to (it.name ?: it.address), "Alamat" to it.address) }
            .sortedBy { it["Nama"] }
        result.success(daftar)
    }

    @SuppressLint("MissingPermission")
    private fun Kirim(alamat: String, data: ByteArray, result: MethodChannel.Result) {
        val adaptor = AmbilAdaptor() ?: return result.error("BluetoothTidakAda", "Perangkat ini tidak punya Bluetooth.", null)
        if (!CekIzin()) {
            return result.error("IzinDitolak", "Izinkan akses Perangkat di sekitar agar printer Bluetooth bisa dipakai.", null)
        }
        if (!adaptor.isEnabled) {
            return result.error("BluetoothMati", "Bluetooth mati. Nyalakan Bluetooth, lalu coba lagi.", null)
        }
        if (!BluetoothAdapter.checkBluetoothAddress(alamat)) {
            return result.error("PrinterTidakDitemukan", "Alamat printer Bluetooth tidak valid. Pilih ulang printer.", null)
        }
        try {
            pekerja.execute { KerjakanCetak(adaptor, alamat, data, result) }
        } catch (galat: java.util.concurrent.RejectedExecutionException) {
            result.error("GagalMengirim", "Printer Bluetooth sedang ditutup. Coba cetak lagi.", galat.message)
        }
    }

    /** Berjalan di utas pekerja: apa pun yang terlempar di sini harus berakhir sebagai jawaban galat, tidak pernah menjatuhkan aplikasi. */
    @SuppressLint("MissingPermission")
    private fun KerjakanCetak(adaptor: BluetoothAdapter, alamat: String, data: ByteArray, result: MethodChannel.Result) {
        var soket: BluetoothSocket? = null
        try {
            val perangkat = adaptor.getRemoteDevice(alamat)
            BatalkanPemindaian(adaptor)
            soket = try {
                BukaSoket(perangkat)
            } catch (galat: IOException) {
                KirimGalat(
                    result,
                    "GagalTersambung",
                    "Printer Bluetooth tidak tersambung. Pastikan printer menyala, sudah di-pair, dan tidak sedang dipakai perangkat lain.",
                    galat.message,
                )
                return
            }
            val keluaran = soket.outputStream
            keluaran.write(data)
            keluaran.flush()
            // Beri waktu printer menarik data dari buffer radio sebelum soket ditutup (printer murah memotong
            // struk bila sambungan diputus terlalu cepat): ±1 detik per 8 KB, minimal 300 ms.
            Thread.sleep(300L + data.size / 8L)
            KirimBerhasil(result)
        } catch (galat: InterruptedException) {
            Thread.currentThread().interrupt()
            KirimGalat(result, "GagalMengirim", "Pencetakan dibatalkan. Coba cetak lagi.", galat.message)
        } catch (galat: Throwable) {
            KirimGalat(result, GalatKeKode(galat), PesanGalat(galat), galat.message)
        } finally {
            try {
                soket?.close()
            } catch (_: Throwable) {
            }
        }
    }

    /**
     * Pemindaian yang berjalan memperlambat dan menggagalkan sambungan RFCOMM. Di Android 12+ membatalkannya butuh izin
     * BLUETOOTH_SCAN yang tidak diminta aplikasi (printer sudah di-pair, tidak perlu memindai), jadi tanpa izin itu
     * dilewati: memanggilnya tanpa izin melempar SecurityException.
     */
    @SuppressLint("MissingPermission")
    private fun BatalkanPemindaian(adaptor: BluetoothAdapter) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S &&
            aktivitas.checkSelfPermission(Manifest.permission.BLUETOOTH_SCAN) != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }
        try {
            if (adaptor.isDiscovering) {
                adaptor.cancelDiscovery()
            }
        } catch (_: SecurityException) {
        }
    }

    /** Soket aman lebih dulu; sebagian printer murah hanya menerima soket tanpa enkripsi atau kanal RFCOMM 1. */
    @SuppressLint("MissingPermission")
    private fun BukaSoket(perangkat: BluetoothDevice): BluetoothSocket {
        val percobaan = listOf<() -> BluetoothSocket>(
            { perangkat.createRfcommSocketToServiceRecord(UUID_SPP) },
            { perangkat.createInsecureRfcommSocketToServiceRecord(UUID_SPP) },
            {
                perangkat.javaClass.getMethod("createRfcommSocket", Int::class.javaPrimitiveType)
                    .invoke(perangkat, 1) as BluetoothSocket
            },
        )
        var galatTerakhir: IOException? = null
        for (buat in percobaan) {
            var soket: BluetoothSocket? = null
            try {
                soket = buat()
                soket.connect()
                return soket
            } catch (galat: IOException) {
                galatTerakhir = galat
                try {
                    soket?.close()
                } catch (_: IOException) {
                }
            } catch (galat: ReflectiveOperationException) {
                galatTerakhir = IOException(galat)
            } catch (galat: IllegalArgumentException) {
                galatTerakhir = IOException(galat)
            }
        }
        throw galatTerakhir ?: IOException("Gagal membuka soket RFCOMM")
    }
}
