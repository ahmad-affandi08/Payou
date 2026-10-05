import 'package:google_sign_in/google_sign_in.dart';

/// D-57: abstraksi "Masuk dengan Google" di perangkat. Mengembalikan token ID Google (JWT) untuk dikirim ke server,
/// atau `null` bila pemilik membatalkan dialog pilih akun. Dipisah dari plugin agar layar bisa diuji dengan tiruan.
abstract interface class PenyediaMasukGoogle {
  Future<String?> AmbilTokenId({required String clientIdServer});
}

final class PenyediaMasukGoogleTidakAda implements PenyediaMasukGoogle {
  const PenyediaMasukGoogleTidakAda();

  @override
  Future<String?> AmbilTokenId({required String clientIdServer}) async => null;
}

/// Pelaksana nyata di Android/iOS lewat `google_sign_in`. `serverClientId` = Client ID web (bidang `aud` token).
final class PenyediaMasukGooglePlugin implements PenyediaMasukGoogle {
  PenyediaMasukGooglePlugin();

  String? _clientIdTerpasang;

  @override
  Future<String?> AmbilTokenId({required String clientIdServer}) async {
    final masuk = GoogleSignIn.instance;
    if (_clientIdTerpasang != clientIdServer) {
      await masuk.initialize(serverClientId: clientIdServer);
      _clientIdTerpasang = clientIdServer;
    }
    try {
      final akun = await masuk.authenticate();
      return akun.authentication.idToken;
    } on GoogleSignInException catch (galat) {
      if (galat.code == GoogleSignInExceptionCode.canceled) {
        return null;
      }
      rethrow;
    }
  }
}
