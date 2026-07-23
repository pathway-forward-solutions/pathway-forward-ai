(function () {
	'use strict';

	var KHOFI_STATES = {
		welcoming: 'Welcoming',
		listening: 'Listening',
		thinking: 'Thinking',
		guiding: 'Guiding',
		celebrating: 'Celebrating',
		concerned: 'Needs clarification',
		handoff: 'Human-support handoff',
		error: 'Error and retry'
	};

	function getConfig() {
		if (typeof window.PFAINavigator !== 'object' || window.PFAINavigator === null) {
			return null;
		}
		return window.PFAINavigator;
	}

	function getGuidedSteps(root, config) {
		var fromRoot = Number(root.getAttribute('data-guided-steps') || 0);
		var fromConfig = Number(config.guidedSteps || 0);
		var steps = fromRoot || fromConfig || 5;
		if (!Number.isFinite(steps)) {
			steps = 5;
		}
		return Math.max(3, Math.min(12, Math.floor(steps)));
	}

	function setStatus(root, message, type) {
		var status = root.querySelector('.pfai-ai-status');
		if (!status) {
			return;
		}
		status.textContent = message || '';
		status.className = 'pfai-ai-status ' + (type ? 'is-' + type : '');
	}

	function announce(root, message) {
		var live = root.querySelector('.pfai-ai-live');
		if (!live) {
			return;
		}
		live.textContent = message || '';
	}

	function setProgress(root, currentStep, totalSteps) {
		var progress = root.querySelector('.pfai-ai-progress');
		if (!progress) {
			return;
		}
		var safeCurrent = Math.max(1, Math.min(totalSteps, currentStep));
		progress.textContent = 'Step ' + safeCurrent + ' of ' + totalSteps;
		announce(root, 'Guided progress updated. Step ' + safeCurrent + ' of ' + totalSteps + '.');
	}

	function isNearBottom(container) {
		var threshold = 72;
		return container.scrollHeight - container.scrollTop - container.clientHeight < threshold;
	}

	function maybeScroll(container, shouldScroll) {
		if (shouldScroll) {
			container.scrollTop = container.scrollHeight;
		}
	}

	function createKhofiAvatar(config, stateKey) {
		var state = KHOFI_STATES[stateKey] ? stateKey : 'guiding';
		var figure = document.createElement('figure');
		figure.className = 'pfai-khofi';
		figure.setAttribute('data-khofi-state', state);
		figure.setAttribute('aria-label', 'Khofi state: ' + KHOFI_STATES[state]);

		var img = document.createElement('img');
		img.className = 'pfai-khofi-image';
		img.src = String(config.khofiImage || '');
		img.alt = 'Khofi, the Pathway Forward AI helper';
		img.loading = 'lazy';
		figure.appendChild(img);

		var caption = document.createElement('figcaption');
		caption.className = 'pfai-khofi-state-label';
		caption.textContent = KHOFI_STATES[state];
		figure.appendChild(caption);

		return figure;
	}

	function classifyAssistantState(text, options) {
		var content = String(text || '').toLowerCase();
		if (options && options.loading) {
			return 'thinking';
		}
		if (options && options.error) {
			return 'error';
		}
		if (options && options.handoff) {
			return 'handoff';
		}
		if (/great|excellent|done|completed|success|submitted successfully/.test(content)) {
			return 'celebrating';
		}
		if (/clarify|which|could you share|need more details|please provide/.test(content)) {
			return 'concerned';
		}
		if (/welcome|hello|glad to help/.test(content)) {
			return 'welcoming';
		}
		return 'guiding';
	}

	function createUserMessage(text) {
		var article = document.createElement('article');
		article.className = 'pfai-ai-message pfai-ai-message-user';
		article.setAttribute('data-message-type', 'user');

		var bubble = document.createElement('div');
		bubble.className = 'pfai-ai-bubble';
		var p = document.createElement('p');
		p.textContent = text;
		bubble.appendChild(p);
		article.appendChild(bubble);

		return article;
	}

	function parseAssistantText(text) {
		var sections = [];
		var actions = [];
		var warnings = [];
		var lines = String(text || '').split(/\r?\n/);
		var current = null;

		lines.forEach(function (rawLine) {
			var line = String(rawLine || '').trim();
			if (!line) {
				return;
			}

			if (/^warning:|^important:|\bemergency\b/i.test(line)) {
				warnings.push(line.replace(/^warning:\s*/i, ''));
				return;
			}

			if (/^\d+[\.)]\s+/.test(line) || /^[-*•]\s+/.test(line)) {
				actions.push(line.replace(/^\d+[\.)]\s+/, '').replace(/^[-*•]\s+/, ''));
				return;
			}

			if (/^[A-Z][A-Za-z\s]{2,40}:$/.test(line)) {
				current = {
					heading: line.replace(/:$/, ''),
					body: []
				};
				sections.push(current);
				return;
			}

			if (!current) {
				current = {
					heading: '',
					body: []
				};
				sections.push(current);
			}
			current.body.push(line);
		});

		if (sections.length === 0 && warnings.length === 0 && actions.length === 0) {
			sections.push({
				heading: '',
				body: [String(text || '')]
			});
		}

		return {
			sections: sections,
			actions: actions,
			warnings: warnings
		};
	}

	function createInstructionCard(type, text) {
		var card = document.createElement('div');
		card.className = 'pfai-ai-callout pfai-ai-callout-' + type;
		card.setAttribute('data-message-type', type);
		card.textContent = text;
		return card;
	}

	function createActionCards(actions) {
		var list = document.createElement('div');
		list.className = 'pfai-ai-action-cards';
		list.setAttribute('aria-label', 'Suggested actions');
		actions.forEach(function (action) {
			var card = document.createElement('div');
			card.className = 'pfai-ai-action-card';
			card.textContent = action;
			list.appendChild(card);
		});
		return list;
	}

	function createAssistantMessage(config, text, options) {
		var state = classifyAssistantState(text, options || {});
		var parsed = parseAssistantText(text);
		var article = document.createElement('article');
		article.className = 'pfai-ai-message pfai-ai-message-assistant';
		article.setAttribute('data-message-type', 'khofi');
		article.appendChild(createKhofiAvatar(config, state));

		var bubble = document.createElement('div');
		bubble.className = 'pfai-ai-bubble pfai-ai-bubble-assistant';

		if ((options && options.loading) === true) {
			bubble.appendChild(createInstructionCard('instruction', 'Khofi is reviewing your request...'));
			article.appendChild(bubble);
			return article;
		}

		if (parsed.warnings.length > 0) {
			parsed.warnings.forEach(function (warning) {
				bubble.appendChild(createInstructionCard('warning', warning));
			});
		}

		var flattened = [];
		parsed.sections.forEach(function (section) {
			if (section.heading) {
				flattened.push({ type: 'heading', value: section.heading });
			}
			section.body.forEach(function (line) {
				flattened.push({ type: 'line', value: line });
			});
		});

		var visibleCount = 0;
		var details = document.createElement('details');
		details.className = 'pfai-ai-more';
		var summary = document.createElement('summary');
		summary.textContent = 'Show more';
		details.appendChild(summary);
		var hasMore = false;

		flattened.forEach(function (entry) {
			var target = bubble;
			if (visibleCount >= 4) {
				target = details;
				hasMore = true;
			}

			if (entry.type === 'heading') {
				var heading = document.createElement('h4');
				heading.className = 'pfai-ai-mini-heading';
				heading.textContent = entry.value;
				target.appendChild(heading);
			} else {
				var paragraph = document.createElement('p');
				paragraph.textContent = entry.value;
				target.appendChild(paragraph);
			}
			visibleCount += 1;
		});

		if (parsed.actions.length > 0) {
			bubble.appendChild(createActionCards(parsed.actions));
			bubble.appendChild(createInstructionCard('complete', 'Action plan prepared. Continue when you are ready.'));
		}

		if (hasMore) {
			bubble.appendChild(details);
		}

		article.appendChild(bubble);
		return article;
	}

	function addMessage(root, element) {
		var log = root.querySelector('.pfai-ai-conversation');
		if (!log) {
			return;
		}
		var shouldScroll = isNearBottom(log);
		log.appendChild(element);
		maybeScroll(log, shouldScroll);
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
		var wrap = root.querySelector('.pfai-ai-prompts');
		if (!wrap) {
			return;
		}
		wrap.innerHTML = '';
		(prompts || []).forEach(function (prompt) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'pfai-ai-prompt';
			button.textContent = prompt;
			button.setAttribute('aria-label', 'Suggested reply: ' + prompt);
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

	function findLatestUserMessage(root) {
		var userMessages = root.querySelectorAll('.pfai-ai-message-user .pfai-ai-bubble p');
		if (!userMessages.length) {
			return '';
		}
		return userMessages[userMessages.length - 1].textContent || '';
	}

	function bindAssistant(root, config) {
		var form = root.querySelector('.pfai-ai-form');
		var input = root.querySelector('.pfai-ai-input');
		var escalateButton = root.querySelector('.pfai-ai-escalate');
		var continueButton = root.querySelector('.pfai-ai-continue');
		var backButton = root.querySelector('.pfai-ai-back');
		var startOverButton = root.querySelector('.pfai-ai-start-over');
		var totalSteps = getGuidedSteps(root, config);
		var conversationId = 0;
		var currentStep = 1;
		var loadingMessage = null;

		if (!form || !input) {
			return;
		}

		setProgress(root, currentStep, totalSteps);

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
				addMessage(root, createAssistantMessage(config, 'Sign in is needed before I can continue guided chat. You can still use Contact Support now.', { handoff: true }));
				return;
			}

			addMessage(root, createUserMessage(message));
			input.value = '';
			setLoading(root, true);
			setStatus(root, 'Khofi is preparing your next step...', 'loading');
			loadingMessage = createAssistantMessage(config, '', { loading: true });
			addMessage(root, loadingMessage);

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
					if (loadingMessage && loadingMessage.parentNode) {
						loadingMessage.parentNode.removeChild(loadingMessage);
					}
					loadingMessage = null;

					if (!data || !data.success) {
						var err = data && data.data && data.data.message ? data.data.message : 'AI is unavailable. Please contact support.';
						setStatus(root, err, 'error');
						toggleSupport(root, true);
						addMessage(root, createAssistantMessage(config, 'I could not complete that request. Please retry or use Contact Support so a coordinator can help.', { error: true, handoff: true }));
						return;
					}

					conversationId = Number(data.data.conversation_id || conversationId || 0);
					currentStep = Math.min(totalSteps, currentStep + 1);
					setProgress(root, currentStep, totalSteps);

					var responseText = String(data.data.response || 'I have an update ready.');
					addMessage(root, createAssistantMessage(config, responseText, {
						handoff: Boolean(data.data.support),
						error: false
					}));
					refreshPromptButtons(root, data.data.next_prompts || []);

					if (data.data.safety) {
						setStatus(root, 'Emergency guidance displayed. Please contact emergency services if needed.', 'warning');
						toggleSupport(root, true);
					} else {
						setStatus(root, 'Next guided step is ready.', 'success');
					}

					if (data.data.support) {
						toggleSupport(root, true);
					}
				})
				.catch(function () {
					setLoading(root, false);
					if (loadingMessage && loadingMessage.parentNode) {
						loadingMessage.parentNode.removeChild(loadingMessage);
					}
					loadingMessage = null;
					setStatus(root, 'A network error occurred. Please retry or contact support.', 'error');
					toggleSupport(root, true);
					addMessage(root, createAssistantMessage(config, 'I hit a network error. Please retry this step or use Contact Support.', { error: true }));
				});
		});

		if (continueButton) {
			continueButton.addEventListener('click', function () {
				if (form.requestSubmit) {
					if (!input.value.trim()) {
						input.value = 'Continue with the next step.';
					}
					form.requestSubmit();
					return;
				}
				var submitEvent = document.createEvent('Event');
				submitEvent.initEvent('submit', true, true);
				if (!input.value.trim()) {
					input.value = 'Continue with the next step.';
				}
				form.dispatchEvent(submitEvent);
			});
		}

		if (backButton) {
			backButton.addEventListener('click', function () {
				if (currentStep > 1) {
					currentStep -= 1;
					setProgress(root, currentStep, totalSteps);
				}
				var latest = findLatestUserMessage(root);
				if (latest) {
					input.value = latest;
				}
				setStatus(root, 'Moved back. Review and revise your previous message.', 'warning');
				input.focus();
			});
		}

		if (startOverButton) {
			startOverButton.addEventListener('click', function () {
				var log = root.querySelector('.pfai-ai-conversation');
				if (log) {
					log.innerHTML = '';
					addMessage(root, createAssistantMessage(config, 'Hello. I am Khofi. Let us start again and move one step at a time.', { }));
				}
				conversationId = 0;
				currentStep = 1;
				setProgress(root, currentStep, totalSteps);
				setStatus(root, 'Started a new guided session.', 'success');
				refreshPromptButtons(root, [
					'I need resume help',
					'I need interview practice',
					'I need job search guidance'
				]);
				input.focus();
			});
		}

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
							addMessage(root, createAssistantMessage(config, 'I could not create a support request this time. Please retry or email support directly.', { error: true }));
							return;
						}

						setStatus(root, String(data.data.message || 'Support request submitted.'), 'success');
						addMessage(root, createAssistantMessage(config, 'Support handoff complete. I summarized your issue for a coordinator follow-up.', { handoff: true }));
						toggleSupport(root, true);
					})
					.catch(function () {
						setStatus(root, 'Unable to submit support request. Please email support.', 'error');
						addMessage(root, createAssistantMessage(config, 'Support handoff failed due to a network issue. Please retry or email support now.', { error: true, handoff: true }));
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
						subtitle.textContent = 'Selected service: ' + card.querySelector('strong').textContent + '. Khofi will guide you one step at a time.';
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
