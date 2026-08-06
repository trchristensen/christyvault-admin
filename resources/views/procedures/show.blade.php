<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $revision->code }} — {{ $revision->title }}</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f6f8; color: #172033; line-height: 1.6; }
        header { background: #1c3366; color: #fff; }
        .header-inner, main, footer { width: min(900px, calc(100% - 32px)); margin: 0 auto; }
        .header-inner { padding: 22px 0; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { font-weight: 800; letter-spacing: .02em; }
        .current { padding: 6px 10px; border-radius: 999px; background: #d1fae5; color: #065f46; font-size: 13px; font-weight: 700; }
        main { margin-top: 24px; margin-bottom: 32px; }
        article { background: #fff; border: 1px solid #dde3ea; border-radius: 14px; box-shadow: 0 8px 30px rgba(23, 32, 51, .07); overflow: hidden; }
        .document-heading, .document-body, .document-footer { padding: 24px; }
        .document-heading { border-bottom: 1px solid #e5e9ef; }
        .meta { display: flex; flex-wrap: wrap; gap: 8px 16px; color: #5b6574; font-size: 14px; }
        h1 { margin: 8px 0 0; font-size: clamp(1.7rem, 5vw, 2.5rem); line-height: 1.18; }
        .summary { margin: 12px 0 0; color: #4b5563; font-size: 1.05rem; }
        .document-body { font-size: 1rem; }
        .document-body h2 { margin: 1.8em 0 .5em; font-size: 1.45rem; line-height: 1.3; }
        .document-body h3 { margin: 1.5em 0 .4em; font-size: 1.18rem; }
        .document-body p, .document-body ul, .document-body ol, .document-body blockquote { margin: .8em 0; }
        .document-body li + li { margin-top: .35em; }
        .document-body blockquote { border-left: 4px solid #f59e0b; background: #fffbeb; padding: 12px 16px; }
        .document-body table { width: 100%; border-collapse: collapse; display: block; overflow-x: auto; }
        .document-body th, .document-body td { border: 1px solid #d7dde5; padding: 8px 10px; text-align: left; }
        .document-body a { color: #1d4ed8; }
        .attachments { padding: 0 24px 24px; }
        .attachments h2 { margin: 0 0 12px; font-size: 1.25rem; }
        .attachment-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .attachment { overflow: hidden; border: 1px solid #dde3ea; border-radius: 12px; background: #fff; }
        .attachment img, .attachment video { display: block; width: 100%; max-height: 360px; background: #f1f5f9; object-fit: contain; }
        .attachment video { background: #000; }
        .attachment-info { padding: 14px; }
        .attachment-title { margin: 0; font-size: 1rem; }
        .attachment-description { margin: 5px 0 0; color: #5b6574; font-size: 14px; }
        .attachment-link { display: inline-block; margin-top: 10px; color: #1d4ed8; font-size: 14px; font-weight: 700; }
        .document-footer { background: #f8fafc; border-top: 1px solid #e5e9ef; color: #5b6574; font-size: 13px; }
        footer { padding-bottom: 28px; color: #687385; font-size: 13px; text-align: center; }
        @media (max-width: 600px) {
            .header-inner { align-items: flex-start; flex-direction: column; }
            .document-heading, .document-body, .document-footer { padding: 18px; }
            .attachments { padding: 0 18px 18px; }
            .attachment-grid { grid-template-columns: 1fr; }
        }
        @media print {
            body { background: #fff; }
            header { background: #fff; color: #172033; border-bottom: 2px solid #1c3366; }
            main { width: 100%; margin-top: 16px; }
            article { border: 0; box-shadow: none; }
            footer { display: none; }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-inner">
            <div class="brand">Christy Vault Company</div>
            <div class="current">Current published procedure</div>
        </div>
    </header>

    <main>
        <article>
            <div class="document-heading">
                <div class="meta">
                    <strong>{{ $revision->code }}</strong>
                    <span>{{ $revision->version_label }}</span>
                    <span>Effective {{ $revision->effective_date->format('M j, Y') }}</span>
                </div>
                <h1>{{ $revision->title }}</h1>
                @if ($revision->summary)
                    <p class="summary">{{ $revision->summary }}</p>
                @endif
            </div>

            <div class="document-body">
                {{ $revision->renderedContent() }}
            </div>

            @if ($revision->attachmentItems(publicOnly: true)->isNotEmpty())
                <section class="attachments" aria-labelledby="related-material-heading">
                    <h2 id="related-material-heading">Related material</h2>
                    <div class="attachment-grid">
                        @foreach ($revision->attachmentItems(publicOnly: true) as $attachment)
                            @php
                                $attachmentUrl = route('procedures.public.attachments.show', [
                                    'token' => $procedure->qr_token,
                                    'attachment' => $attachment['token'],
                                ]);
                            @endphp
                            <article class="attachment">
                                @if ($attachment['media_type'] === 'image')
                                    <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener">
                                        <img src="{{ $attachmentUrl }}" alt="{{ $attachment['title'] }}" loading="lazy">
                                    </a>
                                @elseif ($attachment['media_type'] === 'video')
                                    <video controls preload="metadata">
                                        <source src="{{ $attachmentUrl }}" type="{{ $attachment['mime_type'] }}">
                                        Your browser cannot play this video.
                                    </video>
                                @endif

                                <div class="attachment-info">
                                    <h3 class="attachment-title">{{ $attachment['title'] }}</h3>
                                    @if ($attachment['description'])
                                        <p class="attachment-description">{{ $attachment['description'] }}</p>
                                    @endif
                                    <a class="attachment-link" href="{{ $attachmentUrl }}?download=1">Download file</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <div class="document-footer">
                Verify the procedure number and version before using a printed copy.
                @if ($revision->review_due_date)
                    Review due {{ $revision->review_due_date->format('M j, Y') }}.
                @endif
            </div>
        </article>
    </main>

    <footer>Digitally controlled copy · {{ $revision->code }} · {{ $revision->version_label }}</footer>
</body>
</html>
