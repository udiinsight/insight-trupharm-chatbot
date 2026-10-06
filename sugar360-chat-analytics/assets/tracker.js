/* Sugar360 chatbot analytics tracker. Observes the chat widget from outside; never reads message text. */
(function () {
	'use strict';
	var C = window.S360CA || {};
	if (!C.endpoint || window.__s360caLoaded) return;
	window.__s360caLoaded = true;

	var VID_KEY = 's360ca_vid';
	var ATTR_KEY = 's360ca_attr';
	var WINDOW_SEC = (C.windowDays || 7) * 86400;
	var lastChat = '';

	function rand() {
		var b = new Uint8Array(16);
		(window.crypto || window.msCrypto).getRandomValues(b);
		return Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
	}

	var vidCache = '';
	function visitorId() {
		if (vidCache) return vidCache;
		try {
			var v = localStorage.getItem(VID_KEY);
			if (!v || !/^[a-f0-9]{32}$/.test(v)) {
				v = rand();
				localStorage.setItem(VID_KEY, v);
			}
			vidCache = v;
		} catch (e) {
			vidCache = rand();
		}
		return vidCache;
	}

	function device() {
		var ua = navigator.userAgent || '';
		if (/iPad|Tablet/i.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1)) return 'tablet';
		if (/Mobi|Android|iPhone|iPod/i.test(ua)) return 'mobile';
		return 'desktop';
	}

	function send(event, channel) {
		var body = JSON.stringify({
			event: event,
			channel: channel || '',
			chat_id: lastChat,
			visitor_id: visitorId(),
			page: location.pathname.slice(0, 190),
			device: device()
		});
		try {
			if (navigator.sendBeacon && navigator.sendBeacon(C.endpoint, new Blob([body], { type: 'text/plain' }))) return;
		} catch (e) { /* fall through */ }
		try {
			origFetch.call(window, C.endpoint, { method: 'POST', body: body, keepalive: true, headers: { 'Content-Type': 'text/plain' } });
		} catch (e) { /* analytics must never break the page */ }
	}

	// Remember the last chat for WooCommerce attribution (read back at checkout).
	function markAttribution() {
		if (!lastChat) return;
		var t = Math.floor(Date.now() / 1000);
		try { localStorage.setItem(ATTR_KEY, JSON.stringify({ c: lastChat, t: t })); } catch (e) { /* ignore */ }
		document.cookie = 's360ca_attr=' + lastChat + '|' + t + '|' + visitorId() +
			'; max-age=' + WINDOW_SEC + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
	}

	function readAttribution() {
		try {
			var a = JSON.parse(localStorage.getItem(ATTR_KEY) || 'null');
			if (a && a.c && a.t && (Date.now() / 1000 - a.t) < WINDOW_SEC) return a.c + '|' + a.t + '|' + visitorId();
		} catch (e) { /* ignore */ }
		return '';
	}

	// 1. Mirror the widget's own requests: chat submits (one per visitor message) and its event log.
	var origFetch = window.fetch;
	if (typeof origFetch === 'function') {
		window.fetch = function (input, init) {
			try {
				var url = typeof input === 'string' ? input : (input && input.url) || '';
				var raw = init && typeof init.body === 'string' ? init.body : '';
				if (raw && url.indexOf('mwai-ui/v1/chats/submit') !== -1) {
					var b = JSON.parse(raw);
					if (b && b.chatId) {
						lastChat = String(b.chatId);
						send('message');
						markAttribution();
					}
				} else if (raw && url.indexOf('insight-chat/v1/events') !== -1) {
					var e = JSON.parse(raw);
					var map = { open: 'open', starter_clicked: 'starter', error: 'error' };
					if (e && map[e.event_type]) send(map[e.event_type]);
				}
			} catch (err) { /* never interfere with the widget */ }
			return origFetch.apply(this, arguments);
		};
	}

	// 2. Clicks on the links the bot offers (WhatsApp / phone / contact page / product page).
	function classify(href) {
		if (/^https?:\/\/(wa\.me|api\.whatsapp\.com|web\.whatsapp\.com)\//i.test(href)) return ['handoff', 'whatsapp'];
		if (/^tel:/i.test(href)) return ['handoff', 'phone'];
		if (/\/(customer-service|contact|contact-us|צור-קשר)\/?(\?|#|$)/i.test(decodeURI(href))) return ['handoff', 'contact'];
		if (/\/product\/|add-to-cart=|\/checkout\/?|\/cart\/?(\?|#|$)/i.test(href)) return ['product_click', ''];
		return ['link_click', ''];
	}

	document.addEventListener('click', function (ev) {
		try {
			var a = ev.target && ev.target.closest && ev.target.closest('#insight-chat-root a[href]');
			if (!a) return;
			var kind = classify(a.href || a.getAttribute('href') || '');
			send(kind[0], kind[1]);
			if (kind[0] === 'product_click') markAttribution();
		} catch (err) { /* ignore */ }
	}, true);

	// 3. Classic checkout: carry the attribution in the order form itself (cookie is the fallback).
	function addCheckoutField() {
		var form = document.querySelector('form.checkout, form.woocommerce-checkout');
		if (!form || form.querySelector('input[name="s360ca_attr"]')) return;
		var v = readAttribution();
		if (!v) return;
		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = 's360ca_attr';
		input.value = v;
		form.appendChild(input);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', addCheckoutField);
	} else {
		addCheckoutField();
	}
})();
