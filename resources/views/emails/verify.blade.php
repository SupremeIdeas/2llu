<x-mail.layout heading="Confirm your email">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">Welcome{{ $name ? ', '.$name : '' }} — one quick step</p>
    <p style="margin:0 0 4px;">Please confirm your email address to activate your {{ config('app.name') }} account and start buying eSIMs and numbers.</p>
    <x-mail.button :url="$url">Confirm my email</x-mail.button>
    <p style="margin:0;color:#64748b;font-size:13px;">This link expires in 60 minutes. If you didn't create this account, you can ignore this email.</p>
</x-mail.layout>
