package id.payoung.kasir

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine

class MainActivity : FlutterActivity() {
    private var bluetoothKlasik: KanalBluetoothKlasik? = null
    private var usbPrinter: KanalUsbPrinter? = null
    private var layarPelanggan: KanalLayarPelanggan? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        bluetoothKlasik = KanalBluetoothKlasik(this, flutterEngine.dartExecutor.binaryMessenger)
        usbPrinter = KanalUsbPrinter(this, flutterEngine.dartExecutor.binaryMessenger)
        layarPelanggan = KanalLayarPelanggan(this, flutterEngine.dartExecutor.binaryMessenger)
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        bluetoothKlasik?.TanganiHasilIzin(requestCode, grantResults)
    }

    override fun onDestroy() {
        bluetoothKlasik?.Tutup()
        usbPrinter?.Tutup()
        layarPelanggan?.Tutup()
        super.onDestroy()
    }
}
