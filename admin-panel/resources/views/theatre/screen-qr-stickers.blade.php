<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seat QR stickers — {{ $screen->name }} — {{ $theatre->name }}</title>
    {{--
        swaad-only: the printable armrest stickers that switch on in-seat
        ordering for a screen. Deliberately a standalone page rather than a
        panel screen -- it is printed, not browsed, so it carries no
        sidebar, no nav and a print stylesheet that drops everything
        except the sticker grid.

        The QR codes are drawn in the browser from server-signed URLs.
        Nothing is stored per seat (see App\Support\TheatreSeatToken), so
        reprinting one damaged sticker is just printing one page range.
    --}}
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #cbd5e1;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            background: #f1f5f9;
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }

        .toolbar {
            max-width: 1100px;
            margin: 0 auto 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 16px 20px;
        }

        .toolbar h1 { font-size: 18px; margin: 0 0 4px; }
        .toolbar p { margin: 0; color: var(--muted); font-size: 13px; }
        .toolbar .actions { display: flex; gap: 8px; }

        .btn {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            border-radius: 8px;
            padding: 9px 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-primary { background: #0f172a; color: #fff; border-color: #0f172a; }

        .sheet { max-width: 1100px; margin: 0 auto; }

        .row-block {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .row-title {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 12px;
        }

        .stickers {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }

        .sticker {
            border: 1px dashed var(--line);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            background: #fff;
            /* Never split a single sticker across two sheets of paper. */
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .sticker .venue {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sticker .seat {
            font-size: 20px;
            font-weight: 800;
            line-height: 1.1;
            margin: 2px 0 6px;
        }

        .sticker .screen-name { font-size: 10px; color: var(--muted); font-weight: 600; }
        .sticker canvas { width: 108px !important; height: 108px !important; }
        .sticker .cta { font-size: 9px; color: var(--muted); margin-top: 6px; line-height: 1.3; }

        .empty { text-align: center; color: var(--muted); padding: 48px 0; }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .row-block { border: 0; border-radius: 0; padding: 0; margin: 0 0 8px; }
            .row-title { page-break-after: avoid; }
            .sticker { border-style: solid; border-color: #94a3b8; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <h1>{{ $screen->name }} — seat QR stickers</h1>
            <p>
                {{ $theatre->name }} · {{ collect($rows)->sum(fn ($row) => count($row['seats'])) }} seats.
                Print, cut along the dashed lines, and stick one on each armrest.
            </p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('theatre.screens') }}">Back to screens</a>
            <button class="btn btn-primary" onclick="window.print()">Print</button>
        </div>
    </div>

    <div class="sheet">
        @forelse($rows as $row)
            <div class="row-block">
                <div class="row-title">Row {{ $row['label'] }} — {{ count($row['seats']) }} seats</div>
                <div class="stickers">
                    @foreach($row['seats'] as $seat)
                        <div class="sticker">
                            <div class="venue">{{ $theatre->name }}</div>
                            <div class="seat">{{ $seat['label'] }}</div>
                            <div class="screen-name">{{ $screen->name }}</div>
                            <canvas class="qr" data-url="{{ $seat['url'] }}"></canvas>
                            <div class="cta">Scan to order food to your seat</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="empty">This screen has no seats yet. Design its seat map first.</div>
        @endforelse
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        (function () {
            var canvases = document.querySelectorAll('canvas.qr');

            function draw(index) {
                if (index >= canvases.length) {
                    return;
                }
                var canvas = canvases[index];
                window.QRCode.toCanvas(canvas, canvas.dataset.url, {
                    width: 216,
                    margin: 1,
                    // High correction: these get scanned in the dark, at an
                    // angle, off a sticker that will pick up scuffs.
                    errorCorrectionLevel: 'H',
                }, function () {
                    // One per frame keeps a 300-seat screen from locking the
                    // tab while it renders.
                    window.requestAnimationFrame(function () { draw(index + 1); });
                });
            }

            draw(0);
        })();
    </script>
</body>
</html>
