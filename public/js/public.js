(function () {
	'use strict';

	function getConfig() {
		if (typeof window.PFAINavigator !== 'object' || window.PFAINavigator === null) {
			return null;
		}
		return window.PFAINavigator;
	}

	function setStatus(root, message, type) {
		var status = root.querySelector('.pfai-ai-status');
		if (!status) {
			return;
		}
		status.textContent = message || '';
		status.className = 'pfai-ai-status ' + (type ? 'is-' + type : '');
	}

	function addMessage(root, role, text) {
		var log = root.querySelector('.pfai-ai-conversation');
		if (!log) {
			return;
		}
		var article = document.createElement('article');
		article.className = 'pfai-ai-message pfai-ai-message-' + role;
		var p = document.createElement('p');
		p.textContent = text;
		article.appendChild(p);
		log.appendChild(article);
		log.scrollTop = log.scrollHeight;
	}

	function setLoading(root, loading) {
		var button = root.querySelector('.pfai-ai-send');
		var input = root.querySelector('.pfai-ai-input');
		if (button) {
			button.disabled = loading;
			button.textContent = loading ? 'Sending...' : 'Send';
		}
		if (input) {
			input.setAttribute('aria-busy', loading ? 'true' : 'false');
		}
	}

	function toggleSupport(root, visible) {
		var panel = root.querySelector('.pfai-ai-support');
		if (!panel) {
			return;
		}
		panel.hidden = !visible;
	}

	function refreshPromptButtons(root, prompts) {
		if (!Array.isArray(prompts) || prompts.length === 0) {
			return;
		}
		var wrap = root.querySelector('.pfai-ai-prompts');
		if (!wrap) {
			return;
		}
		wrap.innerHTML = '';
		prompts.forEach(function (prompt) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'pfai-ai-prompt';
			button.textContent = prompt;
			wrap.appendChild(button);
		});
	}

	function selectServiceCard(root, context) {
		var cards = root.querySelectorAll('.pfai-service-card');
		cards.forEach(function (card) {
			var active = card.getAttribute('data-service-context') === context;
			card.classList.toggle('is-active', active);
			card.setAttribute('aria-selected', active ? 'true' : 'false');
		});
	}

	function bindAssistant(root, config) {
		var form = root.querySelector('.pfai-ai-form');
		var input = root.querySelector('.pfai-ai-input');
		var escalateButton = root.querySelector('.pfai-ai-escalate');
		var conversationId = 0;

		if (!form || !input) {
			return;
		}

		if (!config.configured) {
			setStatus(root, 'AI is unavailable right now. Use Contact Support or the fallback request form.', 'warning');
			toggleSupport(root, true);
		}

		root.addEventListener('click', function (event) {
			var promptBtn = event.target.closest('.pfai-ai-prompt');
			if (promptBtn && input) {
				input.value = promptBtn.textContent || '';
				input.focus();
			}
		});

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			var message = (input.value || '').trim();
			if (!message) {
				setStatus(root, 'Enter a message first.', 'error');
				input.focus();
				return;
			}

			if (!config.isAuthenticated) {
				setStatus(root, 'Please sign in to continue. Contact Support is available now.', 'warning');
				toggleSupport(root, true);
				return;
			}

			addMessage(root, 'user', message);
			input.value = '';
			setLoading(root, true);
			setStatus(root, 'Generating guidance...', 'loading');

			var context = root.getAttribute('data-service-context') || config.serviceContext || 'general';
			var body = new URLSearchParams();
			body.set('action', 'pfai_ai_navigator_chat');
			body.set('nonce', config.nonce);
			body.set('message', message);
			body.set('service_context', context);
			body.set('conversation_id', String(conversationId || 0));

			fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: body.toString()
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (data) {
					setLoading(root, false);
					if (!data || !data.success) {
						var err = data && data.data && data.data.message ? data.data.message : 'AI is unavailable. Please contact support.';
						setStatus(root, err, 'error');
						toggleSupport(root, true);
						addMessage(root, 'assistant', 'I could not complete that request. Please use Contact Support so a coordinator can help.');
						return;
					}

					conversationId = Number(data.data.conversation_id || conversationId || 0);
					addMessage(root, 'assistant', String(data.data.response || 'I have an update ready.'));
					refreshPromptButtons(root, data.data.next_prompts || []);

					if (data.data.safety) {
						setStatus(root, 'Emergency guidance displayed. Please contact emergency services if needed.', 'warning');
						toggleSupport(root, true);
					} else {
						setStatus(root, 'Response ready.', 'success');
					}

					if (data.data.support) {
						toggleSupport(root, true);
					}
				})
				.catch(function () {
					setLoading(root, false);
					setStatus(root, 'A network error occurred. Please contact support.', 'error');
					toggleSupport(root, true);
				});
		});

		if (escalateButton) {
			escalateButton.addEventListener('click', function () {
				if (!config.isAuthenticated) {
					setStatus(root, 'Sign in is required to submit a support case. You can still email support.', 'warning');
					toggleSupport(root, true);
					return;
				}

				setStatus(root, 'Submitting support request...', 'loading');
				var context = root.getAttribute('data-service-context') || config.serviceContext || 'general';
				var body = new URLSearchParams();
				body.set('action', 'pfai_ai_navigator_escalate');
				body.set('nonce', config.nonce);
				body.set('service_context', context);
				body.set('conversation_id', String(conversationId || 0));
				body.set('reason', 'Participant requested human support');
				body.set('urgency', 'normal');

				fetch(config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: body.toString()
				})
					.then(function (response) {
						return response.json();
					})
					.then(function (data) {
						if (!data || !data.success) {
							var err = data && data.data && data.data.message ? data.data.message : 'Support request failed.';
							setStatus(root, err, 'error');
							return;
						}

						setStatus(root, String(data.data.message || 'Support request submitted.'), 'success');
						addMessage(root, 'assistant', 'Your support request was submitted successfully. A coordinator will follow up.');
						toggleSupport(root, true);
					})
					.catch(function () {
						setStatus(root, 'Unable to submit support request. Please email support.', 'error');
					});
			});
		}
	}

	function bindReemploymentContext() {
		var wrappers = document.querySelectorAll('.pfai-reemployment-wrapper');
		wrappers.forEach(function (wrapper) {
			wrapper.addEventListener('click', function (event) {
				var card = event.target.closest('.pfai-service-card');
				if (!card) {
					return;
				}
				var context = card.getAttribute('data-service-context') || 'general';
				selectServiceCard(wrapper, context);

				var assistant = wrapper.querySelector('.pfai-ai-navigator');
				if (assistant) {
					assistant.setAttribute('data-service-context', context);
					var subtitle = assistant.querySelector('.pfai-ai-subtitle');
					if (subtitle) {
						subtitle.textContent = 'Selected service: ' + card.querySelector('strong').textContent + '. Tell me what you need help with.';
					}
				}
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var config = getConfig();
		if (!config) {
			return;
		}

		var assistants = document.querySelectorAll('.pfai-ai-navigator');
		assistants.forEach(function (root) {
			bindAssistant(root, config);
		});

		bindReemploymentContext();
	});
})();
