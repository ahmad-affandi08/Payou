import 'package:flutter_test/flutter_test.dart';

import 'package:kasir/Domain/Struk/NomorStruk.dart';

void main() {
  test('nomor struk dicetak tanpa kode perangkat (D-71)', () {
    expect(PendekkanNomorStruk('INV/SLB/260924/POS-001-0042'), 'INV/SLB/260924/0042');
    expect(PendekkanNomorStruk('RJ/UTM/261006/K01-0007'), 'RJ/UTM/261006/0007');
    expect(PendekkanNomorStruk('SO/SLB/260925/POS-001-0001'), 'SO/SLB/260925/0001');
  });

  test('nomor yang tidak berpola dikembalikan apa adanya', () {
    expect(PendekkanNomorStruk('42'), '42');
    expect(PendekkanNomorStruk('TIKET-ABC'), 'TIKET-ABC');
    expect(PendekkanNomorStruk(''), '');
  });
}
