package id.payoung.kasir

import android.app.Activity
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.hardware.usb.UsbConstants
import android.hardware.usb.UsbDevice
import android.hardware.usb.UsbEndpoint
import android.hardware.usb.UsbInterface
import android.hardware.usb.UsbManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import io.flutter.plugin.common.BinaryMessenger
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

/**
 * Printer struk USB (USB host) dan info perangkat untuk printer bawaan all-in-one (PRD §17.2.5, v1.96). Dipanggil dari
 * Dart lewat kanal `id.payoung.kasir/usb-printer`:
 * - `InfoPerangkat` → {Produsen, Model, VersiAndroid}: dipakai deteksi otomatis POS all-in-one (Sunmi, iMin, dll.)
 * - `Daftar` → [{Nama, Alamat}] perangkat USB yang tampak sebagai printer (kelas USB printer, atau kelas vendor dengan
 *   endpoint bulk OUT seperti printer thermal murah & printer bawaan iMin). Alamat = `VID:PID` heksadesimal.
 * - `Kirim` {Alamat, Data} → minta izin USB bila perlu, klaim antarmuka, kirim lewat bulk transfer, lepas.
 * Galat: UsbTidakAda, PrinterTidakDitemukan, IzinDitolak, GagalTersambung, GagalMengirim.
 */
class KanalUsbPrinter(private val aktivitas: Activity, messenger: BinaryMessenger) : MethodChannel.MethodCallHandler {
    companion object {
        const val NAMA_KANAL = "id.payoung.kasir/usb-printer"
        private const val AKSI_IZIN = "id.payoung.kasir.IZIN_USB_PRINTER"
        private const val BATAS_WAKTU_MS = 5000
        private const val UKURAN_POTONGAN = 16 * 1024
    }

    private val kanal = MethodChannel(messenger, NAMA_KANAL)
    private val pekerja: ExecutorService = Executors.newSingleThreadExecutor()
    private val utama = Handler(Looper.getMainLooper())

    init {
        kanal.setMethodCallHandler(this)
    }

    fun Tutup() {
        kanal.setMethodCallHandler(null)
        pekerja.shutdown()
    }

    private fun AmbilManajer(): UsbManager? = aktivitas.getSystemService(Context.USB_SERVICE) as? UsbManager

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "InfoPerangkat" -> result.success(
                mapOf(
                    "Produsen" to Build.MANUFACTURER,
                    "Model" to Build.MODEL,
                    "VersiAndroid" to Build.VERSION.RELEASE,
                ),
            )
            "Daftar" -> {
                val manajer = AmbilManajer() ?: return result.success(emptyList<Map<String, String>>())
                result.success(
                    manajer.deviceList.values
                        .filter { CariEndpointKeluar(it) != null }
                        .map { mapOf("Nama" to NamaPerangkat(it), "Alamat" to Alamat(it)) }
                        .sortedBy { it["Nama"] },
                )
            }
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

    private fun Alamat(perangkat: UsbDevice): String =
        String.format("%04X:%04X", perangkat.vendorId, perangkat.productId)

    private fun NamaPerangkat(perangkat: UsbDevice): String {
        val nama = listOfNotNull(perangkat.manufacturerName, perangkat.productName)
            .map { it.trim() }
            .filter { it.isNotEmpty() }
            .distinct()
            .joinToString(" ")
        return if (nama.isEmpty()) "Printer USB ${Alamat(perangkat)}" else nama
    }

    /** Antarmuka + endpoint bulk OUT: kelas printer (7) diutamakan, lalu kelas vendor (0xFF). */
    private fun CariEndpointKeluar(perangkat: UsbDevice): Pair<UsbInterface, UsbEndpoint>? {
        val antarmuka = (0 until perangkat.interfaceCount).map { perangkat.getInterface(it) }
        val urut = antarmuka.filter { it.interfaceClass == UsbConstants.USB_CLASS_PRINTER } +
            antarmuka.filter { it.interfaceClass == UsbConstants.USB_CLASS_VENDOR_SPEC }
        for (a in urut) {
            for (i in 0 until a.endpointCount) {
                val e = a.getEndpoint(i)
                if (e.type == UsbConstants.USB_ENDPOINT_XFER_BULK && e.direction == UsbConstants.USB_DIR_OUT) {
                    return a to e
                }
            }
        }
        return null
    }

    private fun Kirim(alamat: String, data: ByteArray, result: MethodChannel.Result) {
        val manajer = AmbilManajer() ?: return result.error("UsbTidakAda", "Perangkat ini tidak mendukung USB host.", null)
        val perangkat = manajer.deviceList.values.firstOrNull { Alamat(it).equals(alamat, ignoreCase = true) }
            ?: return result.error(
                "PrinterTidakDitemukan",
                "Printer USB tidak tersambung. Pasang kabel USB printer (pakai OTG bila perlu), nyalakan printer, lalu coba lagi.",
                null,
            )
        if (manajer.hasPermission(perangkat)) {
            KirimDenganIzin(manajer, perangkat, data, result)
            return
        }
        MintaIzin(manajer, perangkat) { diizinkan ->
            if (diizinkan) {
                KirimDenganIzin(manajer, perangkat, data, result)
            } else {
                result.error("IzinDitolak", "Izin memakai printer USB ditolak. Cetak lagi lalu pilih Izinkan.", null)
            }
        }
    }

    private fun MintaIzin(manajer: UsbManager, perangkat: UsbDevice, selesai: (Boolean) -> Unit) {
        val penerima = object : BroadcastReceiver() {
            override fun onReceive(konteks: Context, intent: Intent) {
                if (intent.action != AKSI_IZIN) {
                    return
                }
                try {
                    aktivitas.unregisterReceiver(this)
                } catch (_: IllegalArgumentException) {
                }
                selesai(intent.getBooleanExtra(UsbManager.EXTRA_PERMISSION_GRANTED, false))
            }
        }
        val saring = IntentFilter(AKSI_IZIN)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            aktivitas.registerReceiver(penerima, saring, Context.RECEIVER_NOT_EXPORTED)
        } else {
            @Suppress("UnspecifiedRegisterReceiverFlag")
            aktivitas.registerReceiver(penerima, saring)
        }
        val bendera = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) PendingIntent.FLAG_MUTABLE else 0
        val niat = PendingIntent.getBroadcast(aktivitas, 0, Intent(AKSI_IZIN).setPackage(aktivitas.packageName), bendera)
        manajer.requestPermission(perangkat, niat)
    }

    private fun KirimDenganIzin(manajer: UsbManager, perangkat: UsbDevice, data: ByteArray, result: MethodChannel.Result) {
        pekerja.execute {
            val (antarmuka, endpoint) = CariEndpointKeluar(perangkat) ?: run {
                utama.post { result.error("PrinterTidakDitemukan", "Perangkat USB ini bukan printer yang dikenali.", null) }
                return@execute
            }
            val sambungan = manajer.openDevice(perangkat) ?: run {
                utama.post {
                    result.error("GagalTersambung", "Printer USB tidak bisa dibuka. Cabut lalu pasang lagi kabel USB-nya.", null)
                }
                return@execute
            }
            try {
                if (!sambungan.claimInterface(antarmuka, true)) {
                    utama.post {
                        result.error("GagalTersambung", "Printer USB sedang dipakai aplikasi lain. Tutup aplikasi itu, lalu coba lagi.", null)
                    }
                    return@execute
                }
                var posisi = 0
                while (posisi < data.size) {
                    // bulkTransfer dengan offset baru ada di API 28; salin potongan agar jalan di Android lama.
                    val potongan = data.copyOfRange(posisi, minOf(posisi + UKURAN_POTONGAN, data.size))
                    val terkirim = sambungan.bulkTransfer(endpoint, potongan, potongan.size, BATAS_WAKTU_MS)
                    if (terkirim <= 0) {
                        utama.post {
                            result.error("GagalMengirim", "Struk gagal terkirim ke printer USB. Cek kertas & kabel, lalu cetak lagi.", null)
                        }
                        return@execute
                    }
                    posisi += terkirim
                }
                utama.post { result.success(null) }
            } finally {
                sambungan.releaseInterface(antarmuka)
                sambungan.close()
            }
        }
    }
}
