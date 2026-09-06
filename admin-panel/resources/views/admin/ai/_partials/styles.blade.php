<style>
    /* Shared design system for AI Control Center + related pages -- ports the
       same visual language as resources/views/admin/dashboard.blade.php
       (kpi-card / dash-panel / chart-shell) so this section feels like one
       product instead of a bolted-on admin screen. */
    .ai-shell { display: grid; gap: 22px; }

    /* Page hero -- shared so every AI subpage gets the same heading scale
       (previously only defined inline on ai/index.blade.php). */
    .ai-hero { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; }
    .ai-hero h1 { font-size: 1.5rem; font-weight: 900; color: #0f172a; letter-spacing: -.02em; margin-bottom: 3px; line-height: 1.15; }
    .ai-hero p { color: #64748b; font-weight: 600; font-size: 13px; margin: 0; }

    .ai-grid { display: grid; gap: 16px; }
    .ai-kpi-grid { grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); }

    .ai-kpi-card,
    .ai-panel {
        border: 1px solid rgba(226, 232, 240, .88);
        background:
            linear-gradient(180deg, rgba(255,255,255,.97), rgba(255,255,255,.9)),
            radial-gradient(circle at top right, var(--card-glow, rgba(124,58,237,.12)), transparent 42%);
        box-shadow: 0 18px 45px rgba(15, 23, 42, .06);
        border-radius: 20px;
    }

    .ai-kpi-card { position: relative; padding: 20px; overflow: hidden; min-height: 128px; }
    .ai-kpi-card::after {
        content: ""; position: absolute; inset: auto -30px -50px auto;
        width: 110px; height: 110px; border-radius: 50%;
        background: var(--accent, #7c3aed); opacity: .09;
    }
    .ai-kpi-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; position: relative; z-index: 1; }
    .ai-kpi-icon {
        width: 42px; height: 42px; border-radius: 14px; display: inline-flex;
        align-items: center; justify-content: center; font-size: 17px;
        color: var(--accent, #7c3aed); background: color-mix(in srgb, var(--accent, #7c3aed) 14%, white);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.8);
    }
    .ai-kpi-label { color: #64748b; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; margin-top: 14px; position: relative; z-index: 1; }
    .ai-kpi-value { color: #0f172a; font-size: 24px; font-weight: 950; letter-spacing: -.02em; line-height: 1.1; margin-top: 3px; position: relative; z-index: 1; }
    .ai-kpi-hint { color: #94a3b8; font-size: 11.5px; font-weight: 700; margin-top: 6px; position: relative; z-index: 1; }
    .ai-kpi-badge { font-size: 11px; font-weight: 900; padding: 4px 9px; border-radius: 999px; white-space: nowrap; }

    .ai-panel { overflow: hidden; }
    .ai-panel-head { padding: 18px 20px 12px; display: flex; align-items: center; justify-content: space-between; gap: 14px; }
    .ai-panel-title { margin: 0; color: #0f172a; font-size: 15.5px; font-weight: 950; letter-spacing: -.01em; }
    .ai-panel-sub { color: #64748b; font-size: 12px; font-weight: 600; margin-top: 2px; }
    .ai-panel-link { color: #6d28d9; text-decoration: none; font-size: 12px; font-weight: 900; }
    .ai-panel-body { padding: 0 20px 20px; }
    .ai-chart-shell { height: 260px; padding: 4px 20px 16px; }

    .ai-section-title { font-size: 13px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; color: #475569; margin: 4px 0 12px; }

    .ai-status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 11px; font-weight: 900; background: #eef2ff; color: #4338ca; white-space: nowrap; }
    .ai-status-pill.low, .ai-status-pill.success, .ai-status-pill.executed, .ai-status-pill.allowed, .ai-status-pill.approved { background: #dcfce7; color: #166534; }
    .ai-status-pill.medium, .ai-status-pill.simulated, .ai-status-pill.pending_approval, .ai-status-pill.pending { background: #ffedd5; color: #9a3412; }
    .ai-status-pill.high, .ai-status-pill.blocked, .ai-status-pill.critical, .ai-status-pill.failed, .ai-status-pill.rejected { background: #fee2e2; color: #991b1b; }
    .ai-status-pill.info, .ai-status-pill.logged, .ai-status-pill.not_required { background: #e2e8f0; color: #334155; }

    .ai-row-list { display: grid; gap: 10px; }
    .ai-row-card {
        border: 1px solid rgba(226,232,240,.78); background: rgba(255,255,255,.86);
        border-radius: 16px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; min-width: 0;
    }
    .ai-row-icon {
        width: 38px; height: 38px; border-radius: 12px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center; color: #fff;
        background: linear-gradient(135deg, #111827, #7c3aed);
    }
    .ai-row-body { min-width: 0; flex: 1 1 auto; }
    .ai-row-title { font-weight: 800; color: #0f172a; font-size: 13.5px; }
    .ai-row-meta { color: #64748b; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .ai-exception-card { border-left: 4px solid #ef4444; background: #fff; border-radius: 14px; padding: 13px 16px; box-shadow: 0 4px 14px rgba(15,23,42,.05); }
    .ai-exception-card.severity-medium { border-left-color: #f59e0b; }

    .ai-empty { color: #94a3b8; text-align: center; padding: 34px 12px; font-size: 13px; font-weight: 600; }

    /* Buttons -- shared so every AI subpage renders them consistently
       (previously only defined inline on ai/index.blade.php). */
    .ai-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 7px;
        border-radius: 12px; font-weight: 800; font-size: 13px; line-height: 1;
        padding: 10px 16px; border: 0; cursor: pointer; text-decoration: none;
        transition: filter .15s ease, transform .15s ease;
    }
    .ai-btn:hover { filter: brightness(.97); }
    .ai-btn:active { transform: translateY(1px); }
    .ai-btn-primary { background: linear-gradient(135deg, #7c3aed, #4c1d95); color: #fff; }
    .ai-btn-danger { background: #fee2e2; color: #991b1b; }
    .ai-btn-success { background: #dcfce7; color: #166534; }
    .ai-btn-outline { background: #f1f5f9; color: #334155; }
    .ai-btn-sm { padding: 7px 12px; font-size: 12px; border-radius: 10px; }
</style>
