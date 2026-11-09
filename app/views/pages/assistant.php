<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<?php $gemini_ready = $gemini_ready ?? false; ?>
<section class="assistant-page" aria-labelledby="assistant-title">
	<div class="assistant-heading">
		<p class="eyebrow">A lighter way to plan</p>
		<h1 id="assistant-title">Ask EcoTrip</h1>
		<p class="sub">Ideas for low-carbon routes, thoughtful stays, and trip budgets.</p>
	</div>
	<div class="assistant-window">
		<div id="assistant-messages" class="assistant-messages" role="log" aria-live="polite" aria-relevant="additions text">
			<div class="assistant-message assistant-reply"><p>Hi, I’m your EcoTrip travel assistant. Where are you thinking of going?</p></div>
		</div>
		<div class="assistant-prompts" aria-label="Suggested questions">
			<button type="button" class="assistant-prompt">Plan a 3-day low-carbon trip</button>
			<button type="button" class="assistant-prompt">Compare bus and train travel</button>
			<button type="button" class="assistant-prompt">Help me travel on a budget</button>
		</div>
		<form id="assistant-form" class="assistant-form">
			<?php csrf_field(); ?>
			<label class="visually-hidden" for="assistant-input">Message EcoTrip</label>
			<textarea id="assistant-input" name="message" rows="2" maxlength="1500" placeholder="Ask about your next trip..." required <?= $gemini_ready ? '' : 'disabled' ?>></textarea>
			<button id="assistant-send" class="btn" type="submit" <?= $gemini_ready ? '' : 'disabled' ?>>Send</button>
		</form>
		<p id="assistant-status" class="assistant-status" aria-live="polite"><?= $gemini_ready ? 'Gemini responses are generated on request. Chats are not saved.' : 'Add GEMINI_API_KEY to enable the assistant.' ?></p>
	</div>
</section>
<script>
(() => {
	const form = document.getElementById('assistant-form');
	const input = document.getElementById('assistant-input');
	const send = document.getElementById('assistant-send');
	const messages = document.getElementById('assistant-messages');
	const status = document.getElementById('assistant-status');
	const history = [];

	function appendInline(parent, text) {
		const pattern = /(\*\*[^*]+\*\*|__[^_]+__|`[^`]+`|\*[^*\n]+\*)/g;
		let cursor = 0;
		for (const match of text.matchAll(pattern)) {
			parent.append(document.createTextNode(text.slice(cursor, match.index)));
			const token = match[0];
			const element = token.startsWith('`') ? document.createElement('code')
				: token.startsWith('**') || token.startsWith('__') ? document.createElement('strong')
				: document.createElement('em');
			element.textContent = token.startsWith('**') || token.startsWith('__') ? token.slice(2, -2) : token.slice(1, -1);
			parent.append(element);
			cursor = match.index + token.length;
		}
		parent.append(document.createTextNode(text.slice(cursor)));
	}

	function appendMarkdown(parent, text) {
		const lines = text.replace(/\r/g, '').split('\n');
		let paragraphLines = [];
		let list = null;
		let listType = '';

		function flushParagraph() {
			if (!paragraphLines.length) return;
			const paragraph = document.createElement('p');
			appendInline(paragraph, paragraphLines.join(' '));
			parent.append(paragraph);
			paragraphLines = [];
		}

		function flushList() {
			list = null;
			listType = '';
		}

		for (const rawLine of lines) {
			const line = rawLine.trim();
			if (!line) {
				flushParagraph();
				flushList();
				continue;
			}

			const heading = line.match(/^#{1,4}\s+(.+)$/);
			if (heading) {
				flushParagraph();
				flushList();
				const element = document.createElement('h3');
				appendInline(element, heading[1]);
				parent.append(element);
				continue;
			}

			const bullet = line.match(/^[-*+]\s+(.+)$/);
			const numbered = line.match(/^\d+[.)]\s+(.+)$/);
			if (bullet || numbered) {
				flushParagraph();
				const nextType = numbered ? 'ol' : 'ul';
				if (listType !== nextType) {
					flushList();
					list = document.createElement(nextType);
					listType = nextType;
					parent.append(list);
				}
				const item = document.createElement('li');
				appendInline(item, (bullet || numbered)[1]);
				list.append(item);
				continue;
			}

			flushList();
			paragraphLines.push(line);
		}
		flushParagraph();
	}

	function addMessage(role, text) {
		const bubble = document.createElement('div');
		bubble.className = `assistant-message ${role === 'user' ? 'assistant-user' : 'assistant-reply'}`;
		if (role === 'model') {
			appendMarkdown(bubble, text);
		} else {
			const paragraph = document.createElement('p');
			paragraph.textContent = text;
			bubble.append(paragraph);
		}
		messages.append(bubble);
		bubble.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
	}

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		const text = input.value.trim();
		if (!text || send.disabled) return;
		const previousHistory = history.slice(-10);
		addMessage('user', text);
		input.value = '';
		input.disabled = true;
		send.disabled = true;
		status.textContent = 'Thinking...';

		try {
			const data = new FormData(form);
			data.set('message', text);
			data.set('history', JSON.stringify(previousHistory));
			const response = await fetch('<?= site_url('assistant/message') ?>', {
				method: 'POST',
				headers: { Accept: 'application/json' },
				body: data
			});
			const result = await response.json();
			if (!response.ok) throw new Error(result.error || 'The assistant could not respond.');
			addMessage('model', result.reply);
			history.push({ role: 'user', text }, { role: 'model', text: result.reply });
			status.textContent = 'Chats stay in this browser session.';
		} catch (error) {
			addMessage('model', error.message);
			status.textContent = 'Try another message when you are ready.';
		} finally {
			input.disabled = false;
			send.disabled = false;
			input.focus();
		}
	});

	document.querySelectorAll('.assistant-prompt').forEach((button) => {
		button.addEventListener('click', () => {
			input.value = button.textContent.trim();
			input.focus();
			form.requestSubmit();
		});
	});
})();
</script>
<?php include __DIR__.'/../layouts/footer.php'; ?>