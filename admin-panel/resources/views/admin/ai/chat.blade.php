@extends('layouts.admin')

@section('title', 'AI Chat')
@section('header', 'AI Chat')

@section('styles')
@include('admin.ai._partials.styles')
<style>
    /* ---- ChatGPT-style AI chat ------------------------------------------ */
    .ai-chat-app {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 188px);
        min-height: 460px;
        max-width: 860px;
        margin: 0 auto;
    }
    .ai-chat-topline {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; flex-wrap: wrap; margin-bottom: 14px;
    }
    .ai-chat-topline .ai-chat-status { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

    .ai-chat-scroll {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 8px 4px 24px;
        scroll-behavior: smooth;
    }
    .ai-chat-thread { display: flex; flex-direction: column; gap: 20px; max-width: 740px; margin: 0 auto; }

    .ai-chat-row { display: flex; gap: 14px; align-items: flex-start; }
    .ai-chat-row.from-user { justify-content: flex-end; }

    .ai-chat-avatar {
        width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: .72rem; color: #fff; margin-top: 2px;
        background: linear-gradient(135deg, #111827, #7c3aed);
    }
    .ai-chat-row.from-user .ai-chat-avatar { display: none; }

    .ai-chat-row > div:not(.ai-chat-avatar) { min-width: 0; max-width: 100%; }

    .ai-chat-bubble {
        font-size: .95rem; line-height: 1.6; color: #1f2430;
        white-space: pre-wrap; word-break: break-word;
    }
    .ai-chat-row.from-ai .ai-chat-bubble { padding-right: 8px; }
    .ai-chat-row.from-user .ai-chat-bubble {
        background: #f1f2f6; color: #14171f;
        padding: .7rem 1rem; border-radius: 18px;
        max-width: 72ch;
    }
    .ai-chat-bubble.text-danger { color: #b91c1c; }

    .ai-chat-meta { font-size: .74rem; color: #9aa1a9; margin-top: .45rem; display: flex; align-items: center; gap: .4rem; }
    .ai-chat-meta a { color: #6d28d9; font-weight: 700; text-decoration: none; }

    .ai-chat-typing { display: inline-flex; gap: 4px; padding: .35rem 0; }
    .ai-chat-typing span { width: 7px; height: 7px; border-radius: 50%; background: #c2c7d0; animation: ai-chat-blink 1.2s infinite ease-in-out; }
    .ai-chat-typing span:nth-child(2) { animation-delay: .2s; }
    .ai-chat-typing span:nth-child(3) { animation-delay: .4s; }
    @keyframes ai-chat-blink { 0%, 80%, 100% { opacity: .25; } 40% { opacity: 1; } }

    .ai-chat-approval { display: flex; gap: 8px; margin-top: .6rem; }
    .ai-chat-approval .ai-btn {
        padding: 6px 16px; font-size: 12px; font-weight: 800; border-radius: 999px;
        border: 1px solid transparent; cursor: pointer;
    }
    .ai-chat-approval .ai-btn-success { background: #dcfce7; color: #166534; }
    .ai-chat-approval .ai-btn-danger { background: #fee2e2; color: #991b1b; }
    .ai-chat-approval-outcome { font-size: .82rem; font-weight: 800; margin-top: .55rem; }
    .ai-chat-approval-outcome.approved { color: #166534; }
    .ai-chat-approval-outcome.rejected { color: #991b1b; }

    /* Empty / greeting state */
    .ai-chat-greeting { text-align: center; margin: auto 0; padding: 30px 12px; }
    .ai-chat-greeting-mark {
        width: 54px; height: 54px; border-radius: 16px; margin: 0 auto 16px;
        display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px;
        background: linear-gradient(135deg, #111827, #7c3aed);
        box-shadow: 0 14px 34px rgba(124,58,237,.28);
    }
    .ai-chat-greeting h2 { font-size: 1.25rem; font-weight: 900; color: #0f172a; letter-spacing: -.02em; margin: 0 0 6px; }
    .ai-chat-greeting p { color: #64748b; font-size: .9rem; margin: 0; }

    .ai-chat-suggestions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 20px; }
    .ai-chat-suggestion {
        border: 1px solid #e5e7eb; background: #fff; color: #334155;
        border-radius: 14px; padding: 10px 14px; font-size: .8rem; font-weight: 600;
        text-align: left; max-width: 240px; cursor: pointer; transition: border-color .15s, background .15s;
    }
    .ai-chat-suggestion:hover { border-color: #c7b8f5; background: #faf8ff; }

    /* Composer */
    .ai-chat-composer { padding-top: 10px; }
    .ai-chat-input-bar {
        display: flex; align-items: flex-end; gap: 10px;
        max-width: 740px; margin: 0 auto;
        border: 1px solid #e3e5ea; border-radius: 24px; background: #fff;
        padding: 10px 10px 10px 18px;
        box-shadow: 0 10px 30px rgba(15,23,42,.07);
    }
    .ai-chat-input-bar:focus-within { border-color: #b9a6f2; box-shadow: 0 10px 34px rgba(124,58,237,.16); }
    .ai-chat-input-bar textarea {
        flex: 1 1 auto; resize: none; border: 0; outline: none; background: transparent;
        font-size: .95rem; line-height: 1.5; max-height: 160px; padding: 6px 0; color: #1f2430;
    }
    #ai-chat-send {
        flex-shrink: 0; width: 38px; height: 38px; border-radius: 50%; border: 0; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        background: #111827; color: #fff; transition: opacity .15s, transform .1s;
    }
    #ai-chat-send:hover { opacity: .88; }
    #ai-chat-send:disabled { opacity: .4; cursor: not-allowed; }
    .ai-chat-hint { text-align: center; color: #a2a8b2; font-size: .72rem; margin-top: 8px; }
</style>
@endsection

@section('content')
<div class="ai-chat-app">
    <div class="ai-chat-topline">
        <div class="ai-chat-status">
            <span class="ai-status-pill info">{{ strtoupper($health['autonomy_mode'] ?? 'monitor') }}</span>
            <span class="ai-status-pill {{ ($health['kill_switch'] ?? false) ? 'high' : 'low' }}">
                {{ ($health['kill_switch'] ?? false) ? 'Kill switch on' : 'Guardrails active' }}
            </span>
            <span class="ai-status-pill info">{{ ucfirst($health['provider'] ?? '—') }}</span>
            <span id="ai-chat-conn" class="ai-status-pill info">idle</span>
        </div>
        <a href="{{ route('admin.ai.decisions.index') }}" class="ai-panel-link">Recent activity &rarr;</a>
    </div>

    <div id="ai-chat-messages" class="ai-chat-scroll">
        <div class="ai-chat-thread">
            @isset($question)
                <div class="ai-chat-row from-user">
                    <div><div class="ai-chat-bubble">{{ $question }}</div></div>
                </div>
                <div class="ai-chat-row from-ai">
                    <div class="ai-chat-avatar"><i class="fas fa-robot"></i></div>
                    <div>
                        <div class="ai-chat-bubble">{{ $answer }}</div>
                        @isset($decision)
                            <div class="ai-chat-meta">
                                <a href="{{ route('admin.ai.decisions.show', $decision) }}">Open decision log</a>
                            </div>
                        @endisset
                        @isset($approval)
                            <div class="ai-chat-approval" data-approval-id="{{ $approval->id }}">
                                <button type="button" class="ai-btn ai-btn-success ai-chat-approve">Approve</button>
                                <button type="button" class="ai-btn ai-btn-danger ai-chat-reject">Reject</button>
                            </div>
                        @endisset
                    </div>
                </div>
            @else
                <div class="ai-chat-greeting">
                    <div class="ai-chat-greeting-mark"><i class="fas fa-bolt"></i></div>
                    <h2>How can I help with the business today?</h2>
                    <p>Ask about orders, fleet, finance or accounting &mdash; or ask the AI to plan and run a task within your guardrails.</p>
                    <div class="ai-chat-suggestions">
                        @foreach([
                            'How is business today?',
                            'Which zone is performing poorly?',
                            'How much COD is outstanding?',
                            'Forecast tonight\'s driver requirement.',
                            'Create a gig for tonight\'s dinner rush in the busiest zone.',
                        ] as $suggestion)
                            <button type="button" class="ai-chat-suggestion">{{ $suggestion }}</button>
                        @endforeach
                    </div>
                </div>
            @endisset
        </div>
    </div>

    <div class="ai-chat-composer">
        <form id="ai-chat-form" class="ai-chat-input-bar">
            @csrf
            <textarea id="ai-chat-input" name="message" rows="1" placeholder="Message Swado AI&hellip;" required maxlength="2000"></textarea>
            <button type="submit" id="ai-chat-send" aria-label="Send">
                <i class="fas fa-arrow-up"></i>
            </button>
        </form>
        <div class="ai-chat-hint">AI can make mistakes &mdash; higher-risk actions still queue for your approval.</div>
    </div>

    <noscript>
        <div class="alert alert-warning mt-3">JavaScript is disabled &mdash; use the form below instead.</div>
        <form action="{{ route('admin.ai.chat.ask') }}" method="POST" class="card border-0 shadow-sm p-3 mt-2">
            @csrf
            <textarea name="message" rows="3" class="form-control mb-2" required>{{ old('message') }}</textarea>
            <button class="btn btn-primary">Ask AI</button>
        </form>
    </noscript>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('ai-chat-form');
    var input = document.getElementById('ai-chat-input');
    var scroller = document.getElementById('ai-chat-messages');
    var messages = scroller.querySelector('.ai-chat-thread');
    var sendBtn = document.getElementById('ai-chat-send');
    var connBadge = document.getElementById('ai-chat-conn');
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var chatUrl = @json(route('admin.ai.chat.ask'));
    var approvalsBaseUrl = @json(url('/admin/ai/approvals'));

    function clearGreeting() {
        var greeting = messages.querySelector('.ai-chat-greeting');
        if (greeting) {
            greeting.remove();
        }
    }

    function scrollToBottom() {
        scroller.scrollTop = scroller.scrollHeight;
    }

    function appendUserMessage(text) {
        clearGreeting();
        var row = document.createElement('div');
        row.className = 'ai-chat-row from-user';
        row.innerHTML = '<div><div class="ai-chat-bubble"></div></div>';
        row.querySelector('.ai-chat-bubble').textContent = text;
        messages.appendChild(row);
        scrollToBottom();
    }

    function appendTypingIndicator() {
        clearGreeting();
        var row = document.createElement('div');
        row.className = 'ai-chat-row from-ai';
        row.id = 'ai-chat-typing-row';
        row.innerHTML = '<div class="ai-chat-avatar"><i class="fas fa-robot"></i></div>'
            + '<div><div class="ai-chat-bubble ai-chat-typing"><span></span><span></span><span></span></div></div>';
        messages.appendChild(row);
        scrollToBottom();
        return row;
    }

    function appendAiMessage(answer, decisionUrl, riskLevel, requiresApproval, approvalId) {
        var row = document.createElement('div');
        row.className = 'ai-chat-row from-ai';

        var bubble = document.createElement('div');
        bubble.className = 'ai-chat-bubble';
        bubble.textContent = answer;

        var col = document.createElement('div');
        col.appendChild(bubble);

        if (decisionUrl) {
            var meta = document.createElement('div');
            meta.className = 'ai-chat-meta';
            var link = document.createElement('a');
            link.href = decisionUrl;
            link.textContent = 'Open decision log';
            meta.appendChild(link);
            if (riskLevel) {
                var badge = document.createElement('span');
                badge.className = 'ai-status-pill ' + riskLevel;
                badge.textContent = riskLevel + (requiresApproval ? ' · needs approval' : '');
                meta.appendChild(badge);
            }
            col.appendChild(meta);
        }

        if (approvalId) {
            var approvalBox = document.createElement('div');
            approvalBox.className = 'ai-chat-approval';
            approvalBox.dataset.approvalId = approvalId;
            approvalBox.innerHTML = '<button type="button" class="ai-btn ai-btn-success ai-chat-approve">Approve</button>'
                + '<button type="button" class="ai-btn ai-btn-danger ai-chat-reject">Reject</button>';
            col.appendChild(approvalBox);
        }

        row.innerHTML = '<div class="ai-chat-avatar"><i class="fas fa-robot"></i></div>';
        row.appendChild(col);
        messages.appendChild(row);
        scrollToBottom();
    }

    function resolveApprovalUrl(approvalId, action) {
        return approvalsBaseUrl + '/' + approvalId + '/' + action;
    }

    function handleApprovalClick(box, action) {
        var approvalId = box.dataset.approvalId;
        var buttons = box.querySelectorAll('button');
        buttons.forEach(function (button) { button.disabled = true; });

        fetch(resolveApprovalUrl(approvalId, action), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({}),
        })
            .then(function (response) {
                if (! response.ok) {
                    throw new Error('Request failed with status ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                var outcome = document.createElement('div');
                outcome.className = 'ai-chat-approval-outcome ' + data.status;
                if (data.status === 'approved') {
                    var execStatus = data.action ? data.action.status : null;
                    outcome.textContent = execStatus === 'executed'
                        ? '✅ Approved and executed.'
                        : (execStatus === 'failed' ? '⚠️ Approved, but execution failed.' : '✅ Approved.');
                } else {
                    outcome.textContent = '❌ Rejected.';
                }
                box.replaceWith(outcome);
            })
            .catch(function () {
                buttons.forEach(function (button) { button.disabled = false; });
                appendErrorMessage('Could not reach the AI service. Please try again.');
            });
    }

    messages.addEventListener('click', function (event) {
        var approveBtn = event.target.closest('.ai-chat-approve');
        var rejectBtn = event.target.closest('.ai-chat-reject');
        if (! approveBtn && ! rejectBtn) {
            return;
        }

        var box = event.target.closest('.ai-chat-approval');
        if (! box) {
            return;
        }

        handleApprovalClick(box, approveBtn ? 'approve' : 'reject');
    });

    function appendErrorMessage(text) {
        var row = document.createElement('div');
        row.className = 'ai-chat-row from-ai';
        row.innerHTML = '<div class="ai-chat-avatar"><i class="fas fa-triangle-exclamation"></i></div>'
            + '<div><div class="ai-chat-bubble text-danger"></div></div>';
        row.querySelector('.ai-chat-bubble').textContent = text;
        messages.appendChild(row);
        scrollToBottom();
    }

    function autoGrow() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    }

    input.addEventListener('input', autoGrow);

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && ! event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    document.addEventListener('click', function (event) {
        var chip = event.target.closest('.ai-chat-suggestion');
        if (! chip) {
            return;
        }
        input.value = chip.textContent.trim();
        autoGrow();
        input.focus();
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var message = input.value.trim();
        if (! message) {
            return;
        }

        appendUserMessage(message);
        input.value = '';
        autoGrow();
        input.disabled = true;
        sendBtn.disabled = true;
        connBadge.textContent = 'thinking…';
        connBadge.className = 'ai-status-pill medium';

        appendTypingIndicator();

        fetch(chatUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ message: message }),
        })
            .then(function (response) {
                if (! response.ok) {
                    throw new Error('Request failed with status ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                var typingRow = document.getElementById('ai-chat-typing-row');
                if (typingRow) {
                    typingRow.remove();
                }

                appendAiMessage(data.answer || 'No answer returned.', data.decision_url, data.risk_level, data.requires_approval, data.approval_id);
                connBadge.textContent = 'live';
                connBadge.className = 'ai-status-pill low';
            })
            .catch(function () {
                var typingRow = document.getElementById('ai-chat-typing-row');
                if (typingRow) {
                    typingRow.remove();
                }
                appendErrorMessage('Could not reach the AI service. Please try again.');
                connBadge.textContent = 'error';
                connBadge.className = 'ai-status-pill high';
            })
            .finally(function () {
                input.disabled = false;
                sendBtn.disabled = false;
                input.focus();
            });
    });
});
</script>
@endsection
