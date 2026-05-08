<?php
if (!defined('ABSPATH')) {
    exit;
}

$position = get_option('wtb_widget_position', 'right');
$title = get_option('wtb_widget_title', 'Чат с оператором');
$operator_name = get_option('wtb_operator_name', 'Оператор');
$operator_photo = get_option('wtb_operator_photo', '');
?>

<style><?php include WTB_PLUGIN_DIR . 'public/css/chat-widget.css'; ?></style>

<!-- Chat Launcher -->
<div class="wtb-chat-launcher wtb-launcher-<?php echo esc_attr($position); ?>" id="wtb-chat-launcher" onclick="wtbToggleChat()">
    <?php if ($operator_photo) : ?>
    <img src="<?php echo esc_url($operator_photo); ?>" alt="<?php echo esc_attr($operator_name); ?>" class="wtb-launcher-avatar">
    <?php else : ?>
    <span class="wtb-launcher-icon">💬</span>
    <?php endif; ?>
    <span class="wtb-launcher-status"></span>
</div>

<!-- WP Telegram Bridge Widget -->
<div id="wtb-chat-widget" class="wtb-widget wtb-widget-<?php echo esc_attr($position); ?>" style="display:none;">
    <div class="wtb-chat-header" onclick="wtbToggleChat()">
        <span class="wtb-chat-toggle">✕</span>
    </div>
    
    <div class="wtb-chat-body" id="wtb-chat-body">
        <div class="wtb-chat-tabs">
            <button class="wtb-tab-btn active" data-tab="chat" onclick="wtbSwitchTab('chat')"><?php _e('Чат', 'wp-telegram-bridge'); ?></button>
            <button class="wtb-tab-btn" data-tab="faq" onclick="wtbSwitchTab('faq')"><?php _e('Вопросы', 'wp-telegram-bridge'); ?></button>
        </div>
        <div class="wtb-tab-content">
            <div id="wtb-tab-panel-chat" class="wtb-tab-panel active">
                <!-- Форма ввода имени/email (первый шаг) -->
                <div id="wtb-step-1" class="wtb-step">
                    <div class="wtb-operator-message-row">
                        <?php if ($operator_photo) : ?>
                        <div class="wtb-operator-avatar-wrap">
                            <img src="<?php echo esc_url($operator_photo); ?>" alt="<?php echo esc_attr($operator_name); ?>" class="wtb-operator-avatar">
                            <span class="wtb-operator-status"></span>
                        </div>
                        <?php endif; ?>
                        <div class="wtb-operator-form-bubble">
                            <p class="wtb-greeting"><?php echo esc_html(sprintf(__('Добрый день, меня зовут %s. Давайте вместе решим вашу задачу. Спросите меня :)', 'wp-telegram-bridge'), $operator_name)); ?></p>
                            <p class="wtb-required-hint"><?php _e('* — обязательные поля', 'wp-telegram-bridge'); ?></p>
                            <input type="text" id="wtb-name" placeholder="<?php _e('Ваше имя *', 'wp-telegram-bridge'); ?>" required>
                            <input type="email" id="wtb-email" placeholder="<?php _e('Email *', 'wp-telegram-bridge'); ?>" required>
                            <input type="tel" id="wtb-phone" placeholder="<?php _e('Телефон', 'wp-telegram-bridge'); ?>">
                            <label class="wtb-consent-label">
                                <input type="checkbox" id="wtb-consent" required>
                                <span>Даю согласие на обработку моих персональных данных и принимаю условия <a href="https://franzwertvollen.com/privacy_policy/" rel="noopener noreferrer" target="_blank">политики</a></span>
                            </label>
                            <button id="wtb-start-chat-btn" onclick="wtbStartChat()" disabled><?php _e('Начать чат', 'wp-telegram-bridge'); ?></button>
                        </div>
                    </div>
                </div>
                
                <!-- Чат (второй шаг) -->
                <div id="wtb-step-2" class="wtb-step" style="display:none;">
                    <div id="wtb-messages" class="wtb-messages"></div>
                    <div class="wtb-input-area">
                        <textarea id="wtb-message-input" placeholder="<?php _e('Введите сообщение...', 'wp-telegram-bridge'); ?>" 
                            onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();wtbSendMessage();}"></textarea>
						<button type="button" onclick="wtbSendMessage()" aria-label="Отправить сообщение">
						  <img src="<?php echo esc_url(WTB_PLUGIN_URL . 'public/wtb-input-area-button-img.svg'); ?>" alt="">
						</button> 
				   </div>
				   <div id="wtb-delay-warning" class="wtb-delay-warning" style="display:none;"></div>
                </div>
            </div>
            <div id="wtb-tab-panel-faq" class="wtb-tab-panel">
                <div class="wtb-faq-form">
                    <p class="wtb-faq-intro"><?php _e('У вас есть вопрос? Введите его + место, где нам вам ответить.', 'wp-telegram-bridge'); ?></p>
                    <p class="wtb-required-hint"><?php _e('* — обязательные поля', 'wp-telegram-bridge'); ?></p>
                    <textarea id="wtb-faq-message" placeholder="<?php _e('Ваше сообщение *', 'wp-telegram-bridge'); ?>"></textarea>
                    <input type="text" id="wtb-faq-name" placeholder="<?php _e('Имя *', 'wp-telegram-bridge'); ?>" required>
                    <input type="tel" id="wtb-faq-phone" placeholder="<?php _e('Телефон', 'wp-telegram-bridge'); ?>">
                    <input type="email" id="wtb-faq-email" placeholder="<?php _e('Email *', 'wp-telegram-bridge'); ?>" required>
                    <label class="wtb-consent-label">
                        <input type="checkbox" id="wtb-consent-faq" class="wtb-consent-checkbox">
                        <span>Даю <a href="http://callibri.ru/agreement/43439" rel="noopener noreferrer" target="_blank" class="callibri_b">согласие</a> на обработку моих персональных данных и принимаю условия <a href="http://callibri.ru/privacy/43439" rel="noopener noreferrer" target="_blank" class="callibri_b">политики</a></span>
                    </label>
                    <button id="wtb-submit-faq-btn" onclick="wtbSubmitFaq()" disabled><?php _e('Задать вопрос', 'wp-telegram-bridge'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var wtb_ajax = <?php echo wp_json_encode(array(
    'ajax_url'   => admin_url('admin-ajax.php'),
    'nonce'      => wp_create_nonce('wtb_nonce'),
    'rate_limit' => max(0, intval(get_option('wtb_rate_limit', 5)))
)); ?>;
</script>

<!-- CSP-compatible inline script -->
<script>
// Chat state
window.wtbSessionKey = localStorage.getItem('wtb_session_key') || '';
window.wtbSessionId = localStorage.getItem('wtb_session_id') || '';
window.wtbOperatorName = localStorage.getItem('wtb_operator_name') || '';
window.wtbOperatorPhoto = localStorage.getItem('wtb_operator_photo') || '';
window.wtbRateLimitUntil = 0;
window.wtbRateLimitInterval = null;

// Initialize
if (window.wtbSessionKey && window.wtbSessionId) {
    document.getElementById('wtb-step-1').style.display = 'none';
    document.getElementById('wtb-step-2').style.display = 'block';
    wtbLoadMessages();
    document.getElementById('wtb-chat-widget').style.display = 'block';
    document.getElementById('wtb-chat-launcher').style.display = 'none';
}

function wtbToggleChat() {
    const widget = document.getElementById('wtb-chat-widget');
    const launcher = document.getElementById('wtb-chat-launcher');
    
    if (widget.style.display === 'none' || !widget.style.display) {
        widget.style.display = 'block';
        launcher.style.display = 'none';
    } else {
        widget.style.display = 'none';
        launcher.style.display = 'flex';
    }
}

function wtbSwitchTab(tabName) {
    document.querySelectorAll('.wtb-tab-btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
    });
    document.querySelectorAll('.wtb-tab-panel').forEach(function(panel) {
        panel.classList.toggle('active', panel.id === 'wtb-tab-panel-' + tabName);
    });
}

function wtbSubmitFaq() {
    const message = document.getElementById('wtb-faq-message').value.trim();
    const name = document.getElementById('wtb-faq-name').value.trim();
    const phone = document.getElementById('wtb-faq-phone').value.trim();
    const email = document.getElementById('wtb-faq-email').value.trim();
    
    if (!message) {
        alert('<?php _e("Пожалуйста, введите сообщение", "wp-telegram-bridge"); ?>');
        return;
    }
    
    if (!name) {
        alert('<?php _e("Пожалуйста, введите имя", "wp-telegram-bridge"); ?>');
        return;
    }
    
    if (!email) {
        alert('<?php _e("Пожалуйста, введите email", "wp-telegram-bridge"); ?>');
        return;
    }
    
    if (!document.getElementById('wtb-consent-faq').checked) {
        alert('<?php _e("Пожалуйста, дайте согласие на обработку персональных данных", "wp-telegram-bridge"); ?>');
        return;
    }
    
    jQuery.ajax({
        url: wtb_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'wtb_submit_faq',
            nonce: wtb_ajax.nonce,
            message: message,
            name: name,
            phone: phone,
            email: email
        },
        success: function(response) {
            if (response.success) {
                alert('<?php _e("Спасибо! Ваш вопрос отправлен.", "wp-telegram-bridge"); ?>');
                document.getElementById('wtb-faq-message').value = '';
                document.getElementById('wtb-faq-name').value = '';
                document.getElementById('wtb-faq-phone').value = '';
                document.getElementById('wtb-faq-email').value = '';
            } else {
                alert(response.data.message || '<?php _e("Ошибка отправки", "wp-telegram-bridge"); ?>');
            }
        }
    });
}

function wtbStartChat() {
    const name = document.getElementById('wtb-name').value.trim();
    const email = document.getElementById('wtb-email').value.trim();
    const phone = document.getElementById('wtb-phone').value.trim();
    
    if (!name) {
        alert('<?php _e("Пожалуйста, введите имя", "wp-telegram-bridge"); ?>');
        return;
    }
    
    if (!email) {
        alert('<?php _e("Пожалуйста, введите email", "wp-telegram-bridge"); ?>');
        return;
    }
    
    if (!document.getElementById('wtb-consent').checked) {
        alert('<?php _e("Пожалуйста, дайте согласие на обработку персональных данных", "wp-telegram-bridge"); ?>');
        return;
    }
    
    jQuery.ajax({
        url: wtb_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'wtb_start_session',
            nonce: wtb_ajax.nonce,
            name: name,
            email: email,
            phone: phone
        },
        success: function(response) {
            if (response.success) {
                window.wtbSessionKey = response.data.session_key;
                window.wtbSessionId = response.data.session_id;
                window.wtbOperatorName = response.data.operator_name || '';
                window.wtbOperatorPhoto = response.data.operator_photo || '';
                localStorage.setItem('wtb_session_key', wtbSessionKey);
                localStorage.setItem('wtb_session_id', wtbSessionId);
                localStorage.setItem('wtb_operator_name', window.wtbOperatorName);
                localStorage.setItem('wtb_operator_photo', window.wtbOperatorPhoto);
                
                document.getElementById('wtb-step-1').style.display = 'none';
                document.getElementById('wtb-step-2').style.display = 'block';
            } else {
                alert(response.data.message || 'Error');
            }
        }
    });
}

function wtbResetSession() {
    window.wtbSessionKey = '';
    window.wtbSessionId = '';
    window.wtbOperatorName = '';
    window.wtbOperatorPhoto = '';
    localStorage.removeItem('wtb_session_key');
    localStorage.removeItem('wtb_session_id');
    localStorage.removeItem('wtb_operator_name');
    localStorage.removeItem('wtb_operator_photo');
    document.getElementById('wtb-step-1').style.display = 'block';
    document.getElementById('wtb-step-2').style.display = 'none';
    document.getElementById('wtb-messages').innerHTML = '';
}

function wtbShowWarning(msg) {
    const el = document.getElementById('wtb-delay-warning');
    if (el) {
        el.textContent = msg;
        el.style.display = 'block';
    }
}

function wtbHideWarning() {
    const el = document.getElementById('wtb-delay-warning');
    if (el) {
        el.textContent = '';
        el.style.display = 'none';
    }
}

function wtbStartRateLimitTimer() {
    wtbStopRateLimitTimer();
    wtbUpdateRateLimitWarning();
    window.wtbRateLimitInterval = setInterval(wtbUpdateRateLimitWarning, 1000);
}

function wtbStopRateLimitTimer() {
    if (window.wtbRateLimitInterval) {
        clearInterval(window.wtbRateLimitInterval);
        window.wtbRateLimitInterval = null;
    }
}

function wtbUpdateRateLimitWarning() {
    const remaining = Math.ceil((window.wtbRateLimitUntil - Date.now()) / 1000);
    if (remaining > 0) {
        wtbShowWarning('<?php _e("Подождите", "wp-telegram-bridge"); ?> ' + remaining + ' <?php _e("сек...", "wp-telegram-bridge"); ?>');
    } else {
        wtbHideWarning();
        wtbStopRateLimitTimer();
    }
}

function wtbFormatTime(time) {
    if (!time) return '';
    if (typeof time === 'string' && time.indexOf(':') !== -1 && time.length <= 5) return time;
    let date;
    if (typeof time === 'number') {
        date = new Date(time * 1000);
    } else {
        const parts = time.match(/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/);
        if (!parts) return '';
        date = new Date(parts[1], parts[2] - 1, parts[3], parts[4], parts[5], parts[6]);
    }
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    return hours + ':' + minutes;
}

function wtbSendMessage() {
    const input = document.getElementById('wtb-message-input');
    const content = input.value.trim();
    
    if (!content) return;
    if (window.wtbIsSending) return;
    if (Date.now() < window.wtbRateLimitUntil) {
        wtbStartRateLimitTimer();
        return;
    }
    
    window.wtbIsSending = true;
    wtbHideWarning();
    wtbStopRateLimitTimer();
    
    // Add to UI immediately
    const now = new Date();
    const timeStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
    wtbAddMessage('visitor', content, null, null, timeStr);
    input.value = '';
    
    jQuery.ajax({
        url: wtb_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'wtb_send_message',
            nonce: wtb_ajax.nonce,
            session_key: window.wtbSessionKey,
            content: content
        },
        success: function(response) {
            if (response.success) {
                if (wtb_ajax.rate_limit > 0) {
                    window.wtbRateLimitUntil = Date.now() + (wtb_ajax.rate_limit * 1000);
                }
            } else {
                if (response.data && response.data.code === 'session_not_found') {
                    wtbResetSession();
                    alert('<?php _e("Сессия устарела. Начните чат заново.", "wp-telegram-bridge"); ?>');
                } else if (response.data && response.data.code === 'telegram_error') {
                    alert(response.data.message || '<?php _e("Ошибка отправки в Telegram", "wp-telegram-bridge"); ?>');
                } else {
                    wtbShowWarning(response.data.message || '<?php _e("Ошибка отправки сообщения", "wp-telegram-bridge"); ?>');
                }
            }
        },
        error: function() {
            wtbShowWarning('<?php _e("Не удалось отправить сообщение. Проверьте соединение.", "wp-telegram-bridge"); ?>');
        },
        complete: function() {
            window.wtbIsSending = false;
        }
    });
}

function wtbAddMessage(sender, content, name, photo, time) {
    const container = document.getElementById('wtb-messages');
    
    function makeBubble(cls) {
        const bubble = document.createElement('div');
        bubble.className = cls;
        bubble.textContent = content;
        if (time) {
            const timeEl = document.createElement('div');
            timeEl.className = 'wtb-message-time';
            timeEl.textContent = wtbFormatTime(time);
            bubble.appendChild(timeEl);
        }
        return bubble;
    }
    
    if (sender === 'operator' && (name || photo)) {
        const row = document.createElement('div');
        row.className = 'wtb-message-row wtb-message-row-operator';
        
        if (photo) {
            const avatarWrap = document.createElement('div');
            avatarWrap.className = 'wtb-message-operator-avatar';
            const img = document.createElement('img');
            img.src = photo;
            img.alt = name || '<?php _e('Оператор', 'wp-telegram-bridge'); ?>';
            avatarWrap.appendChild(img);
            row.appendChild(avatarWrap);
        }
        
        const contentWrap = document.createElement('div');
        contentWrap.className = 'wtb-message-operator-content';
        
        if (name) {
            const nameEl = document.createElement('div');
            nameEl.className = 'wtb-message-operator-name';
            nameEl.textContent = name;
            contentWrap.appendChild(nameEl);
        }
        
        const bubble = makeBubble('wtb-message wtb-message-operator');
        contentWrap.appendChild(bubble);
        
        row.appendChild(contentWrap);
        container.appendChild(row);
    } else {
        const bubble = makeBubble('wtb-message wtb-message-' + sender);
        container.appendChild(bubble);
    }
    
    container.scrollTop = container.scrollHeight;
}

function wtbLoadMessages() {
    jQuery.ajax({
        url: wtb_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'wtb_get_messages',
            nonce: wtb_ajax.nonce,
            session_key: window.wtbSessionKey
        },
        success: function(response) {
            if (response.success && response.data.messages) {
                const container = document.getElementById('wtb-messages');
                container.innerHTML = '';
                
                window.wtbOperatorName = response.data.operator_name || '';
                window.wtbOperatorPhoto = response.data.operator_photo || '';
                localStorage.setItem('wtb_operator_name', window.wtbOperatorName);
                localStorage.setItem('wtb_operator_photo', window.wtbOperatorPhoto);
                
                response.data.messages.forEach(function(msg) {
                    const sender = msg.direction === 'to_tg' ? 'visitor' : 'operator';
                    if (sender === 'operator') {
                        wtbAddMessage(sender, msg.content, window.wtbOperatorName, window.wtbOperatorPhoto, msg.created_ts);
                    } else {
                        wtbAddMessage(sender, msg.content, null, null, msg.created_ts);
                    }
                });
            } else if (response.data && response.data.code === 'session_not_found') {
                wtbResetSession();
            }
        }
    });
}

function wtbUpdateConsentState(event) {
    const consentChat = document.getElementById('wtb-consent');
    const consentFaq = document.getElementById('wtb-consent-faq');
    const chatBtn = document.getElementById('wtb-start-chat-btn');
    const faqBtn = document.getElementById('wtb-submit-faq-btn');
    
    // Источник истины: чекбокс, который вызвал событие, или первый доступный при инициализации
    const source = event && event.target ? event.target : (consentChat || consentFaq);
    const checked = source ? source.checked : false;
    
    if (consentChat) consentChat.checked = checked;
    if (consentFaq) consentFaq.checked = checked;
    
    if (chatBtn) chatBtn.disabled = !checked;
    if (faqBtn) faqBtn.disabled = !checked;
}

const consentChat = document.getElementById('wtb-consent');
const consentFaq = document.getElementById('wtb-consent-faq');

if (consentChat) {
    consentChat.addEventListener('change', wtbUpdateConsentState);
}
if (consentFaq) {
    consentFaq.addEventListener('change', wtbUpdateConsentState);
}

wtbUpdateConsentState();

// Poll for new messages every 5 seconds
setInterval(function() {
    if (window.wtbSessionKey) {
        wtbLoadMessages();
    }
}, 5000);
</script>
