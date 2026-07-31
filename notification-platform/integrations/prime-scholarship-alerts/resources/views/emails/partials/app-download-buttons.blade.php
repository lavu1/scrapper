@php
    $iosAppUrl = config('prime.mobile_apps.ios.url');
    $androidAppUrl = config('prime.mobile_apps.android.url');
@endphp

@if ($iosAppUrl || $androidAppUrl)
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px; background:#ffffff; border:1px solid #dbe3ef; border-radius:8px; border-collapse:separate;">
        <tr>
            <td align="center" style="padding:18px 16px 8px;">
                <p style="margin:0 0 12px; color:#0f172a; font-size:15px; line-height:21px; font-weight:800;">Get Prime Scholarship Alerts on your phone</p>
                @if ($androidAppUrl)
                    <a href="{{ $androidAppUrl }}" target="_blank" rel="noopener external" style="display:inline-block; margin:0 5px 10px; padding:11px 16px; border-radius:7px; background:#0f172a; color:#ffffff; font-size:14px; line-height:18px; font-weight:700; text-decoration:none;">Download Android app</a>
                @endif
                @if ($iosAppUrl)
                    <a href="{{ $iosAppUrl }}" target="_blank" rel="noopener external" style="display:inline-block; margin:0 5px 10px; padding:10px 16px; border:1px solid #cbd5e1; border-radius:7px; background:#ffffff; color:#0f172a; font-size:14px; line-height:18px; font-weight:700; text-decoration:none;">Download iPhone app</a>
                @endif
            </td>
        </tr>
    </table>
@endif
