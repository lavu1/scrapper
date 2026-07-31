@php
    $brandLogoUrl = asset('images/brand/prime-scholarship-alerts-logo.png');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New scholarships matching your alert</title>
</head>
<body style="margin:0; padding:0; background:#f3f6fb; color:#111827; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f6fb; margin:0; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px; width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="padding:0 0 14px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <img src="{{ $brandLogoUrl }}" alt="Prime Scholarship Alerts" width="184" style="display:block; max-width:184px; height:auto; border:0;">
                                    </td>
                                    <td align="right" style="vertical-align:middle; font-size:12px; line-height:18px; color:#64748b;">
                                        New scholarship alert
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="border-radius:8px; background:#0f172a; padding:24px 26px;">
                            <p style="margin:0 0 8px; color:#93c5fd; font-size:13px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;">Fresh match found</p>
                            <h1 style="margin:0; color:#ffffff; font-size:26px; line-height:32px; font-weight:800;">New scholarships match your alert</h1>
                            <p style="margin:10px 0 0; color:#dbeafe; font-size:15px; line-height:23px;">Review the details, prepare your documents, and open the scholarship page when you are ready to apply.</p>
                        </td>
                    </tr>

                    @foreach ($scholarships as $scholarship)
                        @php
                            $providerLogoUrl = $scholarship->provider?->logo_url ?? asset('images/brand/provider-default.svg');
                            $documentSource = (string) $scholarship->required_documents;
                            $documentSource = str_replace(['</li>', '<br>', '<br/>', '<br />'], "\n", $documentSource);
                            $documentText = html_entity_decode(strip_tags($documentSource), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $documentItems = collect(preg_split('/\r\n|\r|\n|;/', $documentText))
                                ->flatMap(function ($line) {
                                    $line = trim(preg_replace('/^\s*[-*0-9.)]+/', '', (string) $line));

                                    if (str_contains($line, ',') && str_word_count($line) > 12) {
                                        return preg_split('/,\s*/', $line);
                                    }

                                    return [$line];
                                })
                                ->map(fn ($item) => trim((string) $item, " \t\n\r\0\x0B.-"))
                                ->filter(fn ($item) => strlen($item) > 2)
                                ->unique()
                                ->take(5)
                                ->values();

                            if ($documentItems->isEmpty()) {
                                $documentItems = collect([
                                    'Open the scholarship page and review the listed documents',
                                    'Prepare your CV, transcripts, and identity documents early',
                                    'Confirm eligibility and deadline before starting the application',
                                ]);
                            }
                        @endphp

                        <tr>
                            <td style="padding:18px 0 0;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ffffff; border:1px solid #dbe3ef; border-radius:8px; border-collapse:separate; overflow:hidden;">
                                    <tr>
                                        <td style="padding:22px 24px 8px;">
                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                                <tr>
                                                    <td width="68" style="vertical-align:top; padding-right:16px;">
                                                        <span style="display:block; width:56px; height:56px; border-radius:8px; border:1px solid #dbe3ef; background:#ffffff; overflow:hidden; text-align:center;">
                                                            <img src="{{ $providerLogoUrl }}" alt="{{ $scholarship->provider?->name ?? 'Scholarship provider' }} logo" width="54" height="54" style="display:block; width:54px; height:54px; object-fit:contain; border:0;">
                                                        </span>
                                                    </td>
                                                    <td style="vertical-align:top;">
                                                        <p style="margin:0 0 6px; color:#2563eb; font-size:13px; line-height:18px; font-weight:700;">{{ $scholarship->provider?->name ?? 'Scholarship provider' }}</p>
                                                        <h2 style="margin:0; color:#0f172a; font-size:23px; line-height:30px; font-weight:800;">{{ $scholarship->title }}</h2>
                                                    </td>
                                                </tr>
                                            </table>

                                            <p style="margin:18px 0 0; color:#334155; font-size:15px; line-height:24px;">{{ $scholarship->summary }}</p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="padding:10px 24px 6px;">
                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate; border-spacing:0 8px;">
                                                <tr>
                                                    <td width="50%" style="padding-right:6px;">
                                                        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px;">
                                                            <p style="margin:0 0 4px; color:#1d4ed8; font-size:11px; line-height:15px; font-weight:700; text-transform:uppercase;">Funding</p>
                                                            <p style="margin:0; color:#0f172a; font-size:14px; line-height:20px; font-weight:700;">{{ $scholarship->fundingType?->name ?? 'Not stated' }}</p>
                                                        </div>
                                                    </td>
                                                    <td width="50%" style="padding-left:6px;">
                                                        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px;">
                                                            <p style="margin:0 0 4px; color:#15803d; font-size:11px; line-height:15px; font-weight:700; text-transform:uppercase;">Deadline</p>
                                                            <p style="margin:0; color:#0f172a; font-size:14px; line-height:20px; font-weight:700;">{{ $scholarship->deadline_label }}</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td width="50%" style="padding-right:6px;">
                                                        <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:12px;">
                                                            <p style="margin:0 0 4px; color:#c2410c; font-size:11px; line-height:15px; font-weight:700; text-transform:uppercase;">Host</p>
                                                            <p style="margin:0; color:#0f172a; font-size:14px; line-height:20px; font-weight:700;">{{ $scholarship->hostCountrySummary(4) }}</p>
                                                        </div>
                                                    </td>
                                                    <td width="50%" style="padding-left:6px;">
                                                        <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:8px; padding:12px;">
                                                            <p style="margin:0 0 4px; color:#7e22ce; font-size:11px; line-height:15px; font-weight:700; text-transform:uppercase;">Eligible</p>
                                                            <p style="margin:0; color:#0f172a; font-size:14px; line-height:20px; font-weight:700;">{{ $scholarship->eligibleCountrySummary(4) }}</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="padding:8px 24px 4px;">
                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; border-collapse:separate;">
                                                <tr>
                                                    <td style="padding:16px 16px 12px;">
                                                        <p style="margin:0 0 10px; color:#0f172a; font-size:15px; line-height:20px; font-weight:800;">Document checklist</p>
                                                        @foreach ($documentItems as $document)
                                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:0 0 8px;">
                                                                <tr>
                                                                    <td width="24" style="vertical-align:top;">
                                                                        <span style="display:inline-block; width:18px; height:18px; border-radius:5px; background:#16a34a; color:#ffffff; font-size:12px; line-height:18px; text-align:center; font-weight:700;">&#10003;</span>
                                                                    </td>
                                                                    <td style="vertical-align:top; color:#334155; font-size:14px; line-height:20px;">{{ $document }}</td>
                                                                </tr>
                                                            </table>
                                                        @endforeach
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="padding:18px 24px 24px;">
                                            <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                                <tr>
                                                    <td style="border-radius:7px; background:#2563eb;">
                                                        <a href="{{ route('scholarships.show', $scholarship) }}" style="display:inline-block; padding:13px 20px; color:#ffffff; font-size:15px; line-height:20px; font-weight:800; text-decoration:none;">View scholarship</a>
                                                    </td>
                                                </tr>
                                            </table>
                                            <p style="margin:12px 0 0; color:#64748b; font-size:12px; line-height:18px;">Open the full listing for complete requirements, documents, deadline details, and application steps.</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endforeach

                    <tr>
                        <td>
                            @include('emails.partials.app-download-buttons')
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 4px 0; color:#64748b; font-size:12px; line-height:18px; text-align:center;">
                            You are receiving this because you subscribed to Prime Scholarship Alerts.
                            <br>
                            <a href="{{ $unsubscribeUrl }}" style="color:#2563eb; text-decoration:underline;">Unsubscribe</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
