import 'package:flutter_test/flutter_test.dart';
import 'package:food_delivery_customer/screens/customer/theatre/theatre_seat_link.dart';

/// The seat link is the contract between a printed sticker and the app, and
/// it is signed — so the parse has to be exact. A sticker that parses wrong
/// sends someone else's food to the wrong chair; one that fails to parse
/// silently drops the customer on the home screen mid-film.
void main() {
  group('TheatreSeatLink.parse', () {
    test('reads a sticker URL produced by TheatreSeatToken::path()', () {
      final link = TheatreSeatLink.parse('https://yumma.in/t/1/12/F12/cd69f61262');

      expect(link, isNotNull);
      expect(link!.theatreId, 1);
      expect(link.screenId, 12);
      expect(link.seatLabel, 'F12');
      expect(link.token, 'cd69f61262');
    });

    test('upper-cases the seat so it matches the stored label', () {
      expect(TheatreSeatLink.parse('https://yumma.in/t/1/2/f12/abc1234567')?.seatLabel, 'F12');
    });

    test('decodes an escaped seat label', () {
      expect(TheatreSeatLink.parse('https://yumma.in/t/1/2/F%2012/abc1234567')?.seatLabel, 'F 12');
    });

    test('works when the deployment serves from a subdirectory', () {
      final link = TheatreSeatLink.parse('https://yumma.in/app/t/3/4/A1/abc1234567');

      expect(link?.theatreId, 3);
      expect(link?.seatLabel, 'A1');
    });

    test('ignores every other link on the same domain', () {
      // The app claims only /t on this host; anything else must fall
      // through to the browser rather than opening a seat it invented.
      expect(TheatreSeatLink.parse('https://yumma.in/'), isNull);
      expect(TheatreSeatLink.parse('https://yumma.in/terms'), isNull);
      expect(TheatreSeatLink.parse('https://yumma.in/restaurants/12'), isNull);
      expect(TheatreSeatLink.parse('https://yumma.in/admin/theatres/1/edit'), isNull);
    });

    test('rejects a malformed seat link rather than guessing', () {
      expect(TheatreSeatLink.parse('https://yumma.in/t/1/2/F12'), isNull);
      expect(TheatreSeatLink.parse('https://yumma.in/t/abc/2/F12/abc1234567'), isNull);
      expect(TheatreSeatLink.parse('https://yumma.in/t/1/xyz/F12/abc1234567'), isNull);
      expect(TheatreSeatLink.parse('not a url at all'), isNull);
      expect(TheatreSeatLink.parse(''), isNull);
    });
  });
}
