{{-- Bundled service brand marks (Module 27.5). Simplified single-colour glyphs
     so every OTP/number service shows a recognisable logo even when the
     provider API returns none. Resolution order (ServiceIcons): admin-uploaded
     override → provider API icon → these sprites → letter fallback. Admin can
     replace any of them with an official logo from Admin → Service icons.
     Inline sprite, no CDN (Section 16 icon rules). --}}
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
    <symbol id="svc-whatsapp" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2zm5.2 13.9c-.2.6-1.2 1.1-1.7 1.2-.4.1-1 .1-1.6-.1a13 13 0 0 1-5.9-5.2c-.6-1-1-2.2-.9-3 .1-.6.6-1.5 1.2-1.7.2-.1.5-.1.7 0 .2 0 .5 0 .7.5l.9 2c.1.2.1.4 0 .6l-.5.8c-.1.2-.2.4 0 .7a9 9 0 0 0 3.7 3.3c.3.1.5.1.7-.1l.7-.8c.2-.2.4-.3.6-.2l2 1c.4.2.5.3.5.5 0 .1 0 .3-.1.5z"/>
    </symbol>
    <symbol id="svc-telegram" viewBox="0 0 24 24" fill="currentColor">
        <path d="M21.9 4.4 18.6 19.7c-.2 1-1 1.3-1.9.8l-5-3.7-2.4 2.3c-.3.3-.5.5-1 .5l.4-5.1L18.2 6c.4-.4-.1-.6-.7-.2L6 12.9l-4.9-1.5c-1-.3-1-1 .2-1.5L20.5 3c.9-.3 1.7.2 1.4 1.4z"/>
    </symbol>
    <symbol id="svc-facebook" viewBox="0 0 24 24" fill="currentColor">
        <path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12z"/>
    </symbol>
    <symbol id="svc-google" viewBox="0 0 24 24" fill="currentColor">
        <path d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v3.9h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.7 3-4.3 3-7.4z" opacity=".9"/>
        <path d="M12 22c2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 1-3.4 1a5.9 5.9 0 0 1-5.6-4.1H3.1v2.6A10 10 0 0 0 12 22z"/>
        <path d="M6.4 14a6 6 0 0 1 0-3.9V7.5H3.1a10 10 0 0 0 0 9l3.3-2.5z"/>
        <path d="M12 5.9c1.5 0 2.8.5 3.8 1.5L18.7 4.6A10 10 0 0 0 3.1 7.5L6.4 10A5.9 5.9 0 0 1 12 5.9z"/>
    </symbol>
    <symbol id="svc-instagram" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/>
    </symbol>
    <symbol id="svc-tiktok" viewBox="0 0 24 24" fill="currentColor">
        <path d="M16.7 2h-3.1v13.6a2.9 2.9 0 1 1-2.9-2.9c.3 0 .6 0 .9.1V9.6a6.2 6.2 0 0 0-.9-.1 6.1 6.1 0 1 0 6.1 6.1V8.7a7.7 7.7 0 0 0 4.5 1.4V7a4.6 4.6 0 0 1-3.3-1.4A4.6 4.6 0 0 1 16.7 2z"/>
    </symbol>
    <symbol id="svc-x" viewBox="0 0 24 24" fill="currentColor">
        <path d="M17.8 3h3.1l-6.8 7.8L22 21h-6.3l-4.9-6.4L5.2 21H2.1l7.3-8.3L2 3h6.4l4.4 5.9L17.8 3zm-1.1 16.1h1.7L7.5 4.7H5.7l11 14.4z"/>
    </symbol>
    <symbol id="svc-snapchat" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2.5c3 0 5.2 2.2 5.3 5.2l.1 2c.5.4 1.1.5 1.8.4.3 0 .6.2.7.5.1.4-.1.7-.5.9l-1.6.8c.5 1.6 1.8 3.2 3.6 3.7.4.1.6.5.4.9-.4.8-1.7 1.2-3 1.3-.1.3-.2.7-.4.9-1 .1-1.9.1-2.7.5-.8.3-1.6 1-2.7 1s-1.9-.7-2.7-1c-.8-.4-1.7-.4-2.7-.5-.2-.2-.3-.6-.4-.9-1.3-.1-2.6-.5-3-1.3-.2-.4 0-.8.4-.9 1.8-.5 3.1-2.1 3.6-3.7l-1.6-.8c-.4-.2-.6-.5-.5-.9.1-.3.4-.5.7-.5.7.1 1.3 0 1.8-.4l.1-2c.1-3 2.3-5.2 5.3-5.2z"/>
    </symbol>
    <symbol id="svc-discord" viewBox="0 0 24 24" fill="currentColor">
        <path d="M19.3 5.5A16.9 16.9 0 0 0 15.1 4l-.5 1a15.6 15.6 0 0 0-5.2 0L8.9 4a16.9 16.9 0 0 0-4.2 1.5A17.5 17.5 0 0 0 2 17.3 17 17 0 0 0 7.2 20l1.1-1.8c-.6-.2-1.2-.5-1.7-.9l.4-.3a12.2 12.2 0 0 0 10 0l.4.3c-.5.4-1.1.7-1.7.9L16.8 20a17 17 0 0 0 5.2-2.7A17.5 17.5 0 0 0 19.3 5.5zM9.7 14.9c-1 0-1.8-.9-1.8-2s.8-2 1.8-2 1.8.9 1.8 2-.8 2-1.8 2zm4.6 0c-1 0-1.8-.9-1.8-2s.8-2 1.8-2 1.8.9 1.8 2-.8 2-1.8 2z"/>
    </symbol>
    <symbol id="svc-tinder" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12.5 2.6s.4 3.4-2 5.9c-1-1.3-1-3.2-1-3.2-2.8 1.7-5 4.9-5 8.2A7.7 7.7 0 0 0 12.2 21a7.7 7.7 0 0 0 7.8-7.5c0-4.9-3.9-9.4-7.5-10.9z"/>
    </symbol>
    <symbol id="svc-okcupid" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 21s-7.5-4.9-9.3-9.2C1.1 8 3.2 5 6.2 5c1.9 0 3.7 1 4.6 2.6.2.4.3.7.4 1 .1.4.6.4.8 0 .1-.3.2-.6.4-1A5.3 5.3 0 0 1 17 5c3 0 5.1 3 3.5 6.8C19.5 16.1 12 21 12 21z" opacity=".95"/>
        <circle cx="8.2" cy="10" r="1.3" fill="#fff"/><circle cx="15.8" cy="10" r="1.3" fill="#fff"/>
    </symbol>
    <symbol id="svc-pof" viewBox="0 0 24 24" fill="currentColor">
        <path d="M2 12s3.5-5 8.5-5c2.5 0 4.6 1.2 6.2 2.5L21 7l-1 4.1 1 4.1-4.3-2.5A9.8 9.8 0 0 1 10.5 17C5.5 17 2 12 2 12zm7.2-1.3a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4z"/>
    </symbol>
    <symbol id="svc-uber" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <circle cx="12" cy="12" r="9"/><path d="M8 8v5a4 4 0 0 0 8 0V8"/>
    </symbol>
    <symbol id="svc-apple" viewBox="0 0 24 24" fill="currentColor">
        <path d="M16.4 12.8c0-2.4 2-3.5 2-3.6a4.5 4.5 0 0 0-3.5-1.9c-1.5-.1-2.9.9-3.6.9-.8 0-1.9-.9-3.2-.8a4.7 4.7 0 0 0-4 2.4C2.5 12.7 3.7 17 5.4 19.4c.8 1.1 1.7 2.4 3 2.4 1.2-.1 1.7-.8 3.1-.8s1.9.8 3.2.8c1.3 0 2.2-1.2 3-2.4a10 10 0 0 0 1.3-2.7 4.3 4.3 0 0 1-2.6-3.9zM14 5.6c.7-.8 1.1-1.9 1-3.1-1 .1-2.1.7-2.8 1.5-.6.7-1.1 1.9-1 3 1.1.1 2.2-.6 2.8-1.4z"/>
    </symbol>
    <symbol id="svc-amazon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path d="M4 17c2.5 1.8 5.2 2.7 8 2.7s5.5-.9 8-2.7"/><path d="M18.5 19.5 20 17l-2.9-.3"/>
        <path d="M13.9 12.9c0 1 .1 1.8.5 2.5h-2.2a3.4 3.4 0 0 1-.3-1.2c-.8.9-1.8 1.4-3 1.4-1.8 0-3-1-3-2.7 0-2 1.7-3 4.7-3.3l1.2-.1v-.4c0-1.1-.6-1.6-1.7-1.6-1 0-1.7.4-1.9 1.3H6.1c.2-1.9 1.7-3 4-3 2.6 0 3.8 1.2 3.8 3.6v3.5z"/>
    </symbol>
    <symbol id="svc-netflix" viewBox="0 0 24 24" fill="currentColor">
        <path d="M7 2h3.5l6.5 14.5V2H20.5v20c-1.2-.4-2.4-.6-3.7-.7L10.5 8.8V21.3c-1.2.1-2.4.3-3.5.7V2z"/>
    </symbol>
    <symbol id="svc-paypal" viewBox="0 0 24 24" fill="currentColor">
        <path d="M17.9 6.9c.5-3-1.5-4.9-4.9-4.9H7.2c-.5 0-.9.3-1 .8L3.6 19.4c-.1.4.2.7.6.7h3.4l.6-3.9v.1c.1-.5.5-.8 1-.8h1.8c3.9 0 6.6-1.9 7.4-6 0-.2.1-.4.1-.6.4.2.7.5 1 .8.7 1 .7 2.3.4 3.9-.9 4.4-3.9 5.9-7.8 5.9h-.9c-.4 0-.8.3-.9.8l-.7 4.3c-.1.3.2.6.5.6h3.1c.4 0 .8-.3.9-.7l.6-3.8c.1-.4.5-.7.9-.7h.6c3.4 0 6-1.7 6.8-5.5.5-2.5 0-4.3-1.6-5.4-.3-.2-.6-.4-1-.5z" opacity=".55"/>
        <path d="M16.6 6.3A8 8 0 0 0 14.3 6H9.7c-.4 0-.8.3-.9.7L7.4 15l.2-.8c.1-.5.5-.8 1-.8h1.8c3.9 0 6.6-1.9 7.4-6l.1-.6a4 4 0 0 0-1.3-.5z"/>
    </symbol>
    <symbol id="svc-microsoft" viewBox="0 0 24 24" fill="currentColor">
        <rect x="3" y="3" width="8.5" height="8.5"/><rect x="12.5" y="3" width="8.5" height="8.5" opacity=".8"/><rect x="3" y="12.5" width="8.5" height="8.5" opacity=".8"/><rect x="12.5" y="12.5" width="8.5" height="8.5" opacity=".6"/>
    </symbol>
    <symbol id="svc-viber" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 3H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h2v4l4-4h8a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/><path d="M9 8c.5 2.5 2.5 4.5 5 5"/>
    </symbol>
    <symbol id="svc-signal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="2.5 2.5">
        <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4.5" fill="currentColor" stroke="none" stroke-dasharray="0"/>
    </symbol>
    <symbol id="svc-linkedin" viewBox="0 0 24 24" fill="currentColor">
        <path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9h4v12H3zM9.5 9h3.8v1.7h.1c.5-1 1.8-2 3.7-2 4 0 4.7 2.6 4.7 6V21h-4v-5.6c0-1.3 0-3.1-1.9-3.1s-2.2 1.5-2.2 3V21h-4V9z"/>
    </symbol>
    <symbol id="svc-wechat" viewBox="0 0 24 24" fill="currentColor">
        <path d="M9.2 4C5.2 4 2 6.7 2 10c0 1.9 1 3.5 2.7 4.6l-.7 2.1 2.4-1.2c.6.2 1.2.3 1.9.4a5.5 5.5 0 0 1-.2-1.5c0-3.2 3-5.8 6.7-5.8h.5C14.7 6 12.2 4 9.2 4zM6.9 7.4a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8zm4.6 0a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8zM15 9.7c-3.3 0-6 2.2-6 5s2.7 5 6 5c.6 0 1.2-.1 1.8-.2l2.1 1.1-.6-1.9C19.9 17.8 21 16.3 21 14.6c0-2.7-2.7-4.9-6-4.9zm-2 2.6a.8.8 0 1 1 0 1.6.8.8 0 0 1 0-1.6zm4 0a.8.8 0 1 1 0 1.6.8.8 0 0 1 0-1.6z"/>
    </symbol>
</svg>
