/*
 * EcoTrip motion layer — vanilla JS, no dependencies.
 *
 * Features
 *  - Letter-roll hover on [data-roll] links/labels
 *  - Letter-by-letter rise-in on [data-split] (hero wordmark, footer wordmark)
 *  - Scroll reveals on [data-reveal] (+ automatic reveals on inner pages)
 *  - Parallax images [data-parallax], scroll-grown hero frame [data-grow]
 *  - Words that light up as you scroll [data-words]
 *  - Full-screen menu, header that hides on scroll-down, soft page transitions
 *  - Magnetic buttons [data-magnetic] (pointer devices only)
 *
 * Safety: the script never touches the React app mounted at #trips-react-app,
 * and everything degrades to plain, fully visible content if it fails to run.
 */
(() => {
	'use strict';

	const doc = document.documentElement;
	const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
	const REACT_ROOT = '#trips-react-app';
	const $ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
	const insideReact = (el) => !!el.closest(REACT_ROOT) || !!el.querySelector(REACT_ROOT);

	function ready(fn) {
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true });
		else fn();
	}

	/* ------------------------------------------------------------------ *
	 * Text splitting
	 * ------------------------------------------------------------------ */

	/** Wrap every character of every text node in <span class="ch">, keeping element structure. */
	function splitChars(root, startIndex = 0) {
		let index = startIndex;
		const walk = (node) => {
			Array.from(node.childNodes).forEach((child) => {
				if (child.nodeType === Node.TEXT_NODE) {
					const frag = document.createDocumentFragment();
					Array.from(child.textContent).forEach((ch) => {
						const span = document.createElement('span');
						span.className = 'ch';
						span.style.setProperty('--i', String(index++));
						span.textContent = ch;
						frag.appendChild(span);
					});
					node.replaceChild(frag, child);
				} else if (child.nodeType === Node.ELEMENT_NODE) {
					walk(child);
				}
			});
		};
		walk(root);
		return index;
	}

	function initSplit() {
		let wordmarkIndex = 0;
		$('[data-split]').forEach((el) => {
			if (insideReact(el)) return;
			const inFooter = !!el.closest('[data-footer-mark]');
			const next = splitChars(el, inFooter ? 0 : wordmarkIndex);
			if (!inFooter) wordmarkIndex = next;
		});
	}

	/** Letter-roll: duplicates the label so the second copy slides up on hover. */
	function initRoll() {
		$('[data-roll]').forEach((el) => {
			if (insideReact(el) || el.querySelector('.roll')) return;
			const label = el.textContent.trim();
			if (!label) return;

			const roll = document.createElement('span');
			roll.className = 'roll';
			roll.setAttribute('aria-hidden', 'true');

			const makeRow = (extra) => {
				const row = document.createElement('span');
				row.className = 'roll-row' + (extra ? ' ' + extra : '');
				Array.from(label).forEach((ch, i) => {
					const span = document.createElement('span');
					span.className = 'ch';
					span.style.setProperty('--i', String(i));
					span.textContent = ch;
					row.appendChild(span);
				});
				return row;
			};
			roll.append(makeRow(), makeRow('roll-clone'));

			const isLinkLike = el.matches('a, button');
			const host = isLinkLike ? el : el.closest('a, button') || el;
			if (isLinkLike && !el.hasAttribute('aria-label')) el.setAttribute('aria-label', label);
			else if (!isLinkLike && !host.hasAttribute('aria-label') && host !== el) host.setAttribute('aria-label', host.textContent.replace(/\s+/g, ' ').trim());

			el.textContent = '';
			el.appendChild(roll);
			host.classList.add('roll-host');
		});
	}

	/** Words that light up while the paragraph travels through the viewport. */
	let wordBlocks = [];
	function initWords() {
		wordBlocks = $('[data-words]').map((el) => {
			const text = el.textContent.trim().split(/\s+/);
			el.setAttribute('aria-label', el.textContent.trim());
			el.textContent = '';
			const words = text.map((word, i) => {
				const span = document.createElement('span');
				span.className = 'w';
				span.setAttribute('aria-hidden', 'true');
				span.textContent = word;
				el.appendChild(span);
				if (i < text.length - 1) el.appendChild(document.createTextNode(' '));
				return span;
			});
			return { el, words };
		});
	}

	/* ------------------------------------------------------------------ *
	 * Scroll reveal
	 * ------------------------------------------------------------------ */

	/** On ordinary app pages, softly reveal the main building blocks without touching markup. */
	function autoReveal() {
		const main = document.querySelector('main');
		if (!main || main.querySelector('.hero')) return;
		const targets = $(
			'main > h1, main > .sub, main > .eyebrow, main > .card, main > form, main > .grid > *, main > section, ' +
			'main > .journal-grid > *, main > table, .journey-card, .dashboard-stat, .dashboard-discover a, ' +
			'.admin-section, .admin-metrics > div, .receipt-sheet, .assistant-window, .route-list'
		);
		targets.forEach((el, i) => {
			if (el.hasAttribute('data-reveal') || insideReact(el) || el.closest('[data-reveal]')) return;
			el.setAttribute('data-reveal', '');
			const sibling = Array.from(el.parentElement.children).indexOf(el);
			el.style.setProperty('--d', Math.min(sibling, 8) * 70 + 'ms');
		});
	}

	let observer = null;
	function initReveal() {
		const items = $('[data-reveal]');
		if (!items.length) return;

		// Stagger groups: children of [data-stagger] reveal one after another.
		$('[data-stagger]').forEach((group) => {
			Array.from(group.children).forEach((child, i) => {
				if (!child.style.getPropertyValue('--d')) child.style.setProperty('--d', i * 130 + 'ms');
				$('[data-reveal="img"]', child).forEach((img) => img.style.setProperty('--d', i * 130 + 120 + 'ms'));
			});
		});

		if (reduceMotion || !('IntersectionObserver' in window)) {
			items.forEach((el) => el.classList.add('is-in'));
			return;
		}
		// Clip-path "image wipes" are fully clipped before they reveal, and the browser treats a fully
		// clipped element as not intersecting, so those are triggered via their (visible) parent instead.
		const watched = new Map();
		items.forEach((el) => {
			const watchTarget = el.getAttribute('data-reveal') === 'img' && el.parentElement ? el.parentElement : el;
			if (!watched.has(watchTarget)) watched.set(watchTarget, []);
			watched.get(watchTarget).push(el);
		});
		observer = new IntersectionObserver((entries) => {
			entries.forEach((entry) => {
				if (!entry.isIntersecting) return;
				(watched.get(entry.target) || []).forEach((el) => el.classList.add('is-in'));
				observer.unobserve(entry.target);
			});
		}, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
		watched.forEach((_, target) => observer.observe(target));

		// Footer wordmark rises as it enters.
		const mark = document.querySelector('[data-footer-mark]');
		if (mark) {
			const markObserver = new IntersectionObserver((entries) => {
				entries.forEach((entry) => {
					if (entry.isIntersecting) { mark.classList.add('is-in'); markObserver.disconnect(); }
				});
			}, { threshold: 0.25 });
			markObserver.observe(mark);
		}
	}

	/* ------------------------------------------------------------------ *
	 * Scroll-linked effects (single rAF loop)
	 * ------------------------------------------------------------------ */

	const header = () => document.getElementById('site-header');
	let lastY = window.scrollY;
	let ticking = false;
	const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));

	function onScroll() {
		if (ticking) return;
		ticking = true;
		requestAnimationFrame(() => { update(); ticking = false; });
	}

	function update() {
		const y = window.scrollY;
		const vh = window.innerHeight;

		// Header: hide when scrolling down, show on scroll up (never while the menu is open).
		const h = header();
		if (h) {
			const delta = y - lastY;
			if (Math.abs(delta) > 6) {
				h.classList.toggle('is-hidden', delta > 0 && y > 160);
				lastY = y;
			}
			if (y < 80) h.classList.remove('is-hidden');
		}

		if (reduceMotion) return;

		// Parallax images
		$('[data-parallax]').forEach((el) => {
			const frame = el.parentElement.getBoundingClientRect();
			if (frame.bottom < -100 || frame.top > vh + 100) return;
			const speed = parseFloat(el.dataset.parallax) || 0.1;
			const offset = (frame.top + frame.height / 2 - vh / 2) * -speed;
			el.style.transform = `translate3d(0, ${offset.toFixed(1)}px, 0)`;
		});

		// Hero frame grows from a rounded inset card to full-bleed as you scroll.
		$('[data-grow]').forEach((el) => {
			const rect = el.getBoundingClientRect();
			const progress = clamp((vh * 0.92 - rect.top) / (vh * 0.75));
			el.style.setProperty('--p', progress.toFixed(3));
		});

		// Statement words
		wordBlocks.forEach(({ el, words }) => {
			const rect = el.getBoundingClientRect();
			const progress = clamp((vh * 0.86 - rect.top) / (vh * 0.5 + rect.height * 0.6));
			const lit = Math.round(progress * words.length);
			words.forEach((w, i) => w.classList.toggle('on', i < lit));
		});
	}

	/* ------------------------------------------------------------------ *
	 * Menu
	 * ------------------------------------------------------------------ */

	function initMenu() {
		const toggle = document.getElementById('menu-toggle');
		const menu = document.getElementById('site-menu');
		const h = header();
		if (!toggle || !menu) return;

		const focusables = () => $('a[href], button:not([disabled])', menu);

		function setOpen(open) {
			toggle.setAttribute('aria-expanded', String(open));
			toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
			menu.classList.toggle('is-open', open);
			menu.setAttribute('aria-hidden', String(!open));
			document.body.classList.toggle('menu-open', open);
			if (h) { h.classList.toggle('is-menu-open', open); if (open) h.classList.remove('is-hidden'); }
			if (open) {
				const first = focusables()[0];
				if (first) setTimeout(() => first.focus({ preventScroll: true }), 350);
			}
		}

		toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
		menu.addEventListener('click', (e) => { if (e.target.closest('a')) setOpen(false); });
		document.addEventListener('keydown', (e) => {
			const open = toggle.getAttribute('aria-expanded') === 'true';
			if (!open) return;
			if (e.key === 'Escape') { setOpen(false); toggle.focus(); return; }
			if (e.key === 'Tab') {
				const items = [toggle, ...focusables()];
				const first = items[0];
				const last = items[items.length - 1];
				if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
				else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
			}
		});
	}

	/* ------------------------------------------------------------------ *
	 * Page transitions & magnetic buttons
	 * ------------------------------------------------------------------ */

	function initPageTransitions() {
		if (reduceMotion) return;
		document.addEventListener('click', (e) => {
			if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
			const a = e.target.closest('a[href]');
			if (!a || a.target === '_blank' || a.hasAttribute('download') || a.closest(REACT_ROOT)) return;
			let url;
			try { url = new URL(a.href, location.href); } catch (_) { return; }
			if (url.origin !== location.origin) return;
			if (url.pathname === location.pathname && url.search === location.search) return; // same page / hash links
			e.preventDefault();
			document.body.classList.add('is-leaving');
			setTimeout(() => { location.href = url.href; }, 260);
			// If the link turns out to be a file download the page stays put, so never leave it faded.
			setTimeout(() => document.body.classList.remove('is-leaving'), 3000);
		});
		// Coming back via the browser's back button should never show a faded page.
		window.addEventListener('pageshow', (e) => { if (e.persisted) document.body.classList.remove('is-leaving'); });
	}

	function initMagnetic() {
		if (reduceMotion || !finePointer) return;
		$('[data-magnetic]').forEach((el) => {
			let raf = 0;
			el.addEventListener('pointermove', (e) => {
				const r = el.getBoundingClientRect();
				const x = (e.clientX - (r.left + r.width / 2)) * 0.22;
				const y = (e.clientY - (r.top + r.height / 2)) * 0.32;
				cancelAnimationFrame(raf);
				raf = requestAnimationFrame(() => { el.style.transform = `translate(${x.toFixed(1)}px, ${y.toFixed(1)}px)`; });
			});
			el.addEventListener('pointerleave', () => {
				cancelAnimationFrame(raf);
				el.style.transition = 'transform .6s cubic-bezier(.16,.84,.24,1)';
				el.style.transform = '';
				setTimeout(() => { el.style.transition = ''; }, 600);
			});
		});
	}

	/* ------------------------------------------------------------------ */

	/** Photos are decorative: if one fails to load, remove it so the tinted fallback shows cleanly. */
	function initImageFallbacks() {
		const selector = '.feature-media img, .hero-frame img';
		const drop = (img) => { if (img.matches(selector)) img.remove(); };
		document.addEventListener('error', (e) => { if (e.target && e.target.tagName === 'IMG') drop(e.target); }, true);
		$(selector).forEach((img) => { if (img.complete && img.naturalWidth === 0) drop(img); });
	}

	ready(() => {
		try {
			initImageFallbacks();
			initSplit();
			initRoll();
			initWords();
			autoReveal();
			initReveal();
			initMenu();
			initPageTransitions();
			initMagnetic();
			update();
			window.addEventListener('scroll', onScroll, { passive: true });
			window.addEventListener('resize', onScroll, { passive: true });
		} catch (error) {
			// Never let a decorative effect hide content.
			doc.classList.remove('js');
			if (window.console) console.error('EcoTrip motion disabled:', error);
		}
		window.__ecoMotionReady = true;
	});
})();
