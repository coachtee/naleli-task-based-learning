<style>
:root{
  --navy:#0A1F3D;--navy-2:#132A4D;--coral:#FF6A3D;--coral-dark:#E14F22;
  --bg:#F5F6F9;--card:#FFFFFF;--line:#E7E9F0;--ink:#111827;--muted:#66708B;--faint:#9AA4BD;
  --success:#12B76A;--success-bg:#E9FBF1;--warning:#F79009;--warning-bg:#FFF6E5;
  --danger:#F04438;--danger-bg:#FEECEB;--info:#2E90FA;--info-bg:#EAF3FE;
  --violet:#7A5AF8;--violet-bg:#F1EEFE;--teal:#15B79E;--teal-bg:#E7FAF6;--rose:#F63D68;--rose-bg:#FFEAEF;
  --coral-bg:#FFEDE6;--gray-bg:#F0F1F5;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{height:100%}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;padding-bottom:78px}
button{font:inherit;border:none;background:none;cursor:pointer;-webkit-tap-highlight-color:transparent}
a{color:inherit;text-decoration:none}
.hide{display:none!important}
:focus-visible{outline:2.5px solid var(--coral);outline-offset:2px;border-radius:6px}

/* Tab-root header (Dashboard, Leads, Records, More) */
.topbar{position:sticky;top:0;z-index:5;background:var(--navy);color:#fff;display:flex;align-items:center;gap:12px;padding:16px 16px 14px}
.topbar h1{flex:1;margin:0;font-size:18px;font-weight:800;letter-spacing:-.2px}
.topbar .sub{display:block;font-size:11.5px;font-weight:500;color:rgba(255,255,255,.6);margin-top:1px}
.mark{width:30px;height:30px;border-radius:8px;background:#fff;display:grid;place-items:center;flex-shrink:0}
.mark img{width:21px;height:21px;object-fit:contain;display:block}
.iconbtn{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.13);color:#fff;position:relative;flex-shrink:0}
.iconbtn svg{width:18px;height:18px}
.iconbtn .dot{position:absolute;top:6px;right:7px;width:7px;height:7px;border-radius:50%;background:var(--coral);border:1.5px solid var(--navy)}
.avatar{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;font-size:12px;font-weight:800;color:#fff;flex-shrink:0}

/* Detail-screen header (Lead profile, Learner profile) */
.topbar-plain{position:sticky;top:0;z-index:5;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:10px;padding:12px 10px}
.backbtn{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;color:var(--ink);flex-shrink:0}
.backbtn svg{width:20px;height:20px}
.topbar-plain .ttl{flex:1;min-width:0}
.topbar-plain .ttl .name{font-size:15.5px;font-weight:800;color:var(--ink)}
.kebab{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;color:var(--muted);flex-shrink:0}
.kebab svg{width:19px;height:19px}

/* Status pills */
.pill{font-size:10.5px;font-weight:800;padding:2.5px 8px;border-radius:100px;white-space:nowrap;display:inline-block}
.pill-lead{background:var(--coral-bg);color:var(--coral-dark)}
.pill-contacted{background:var(--info-bg);color:var(--info)}
.pill-registration_started{background:var(--violet-bg);color:var(--violet)}
.pill-awaiting_payment{background:var(--warning-bg);color:var(--warning)}
.pill-paid{background:var(--teal-bg);color:var(--teal)}
.pill-profile_incomplete{background:var(--rose-bg);color:var(--rose)}
.pill-registered{background:var(--success-bg);color:var(--success)}
.pill-withdrawn,.pill-rejected{background:var(--gray-bg);color:var(--muted)}
/* Learner statuses, reusing the same hues */
.pill-prospect{background:var(--coral-bg);color:var(--coral-dark)}
.pill-applicant{background:var(--violet-bg);color:var(--violet)}
.pill-active{background:var(--success-bg);color:var(--success)}
.pill-alumni{background:var(--info-bg);color:var(--info)}
.pill-suspended{background:var(--warning-bg);color:var(--warning)}

/* Cards */
.section-hd{display:flex;align-items:center;justify-content:space-between;padding:20px 16px 8px}
.section-hd h2{font-size:15px;font-weight:800;margin:0}
.section-hd .link{font-size:12.5px;font-weight:700;color:var(--coral-dark)}
.card-list{display:flex;flex-direction:column;gap:10px;padding:0 16px}
.row-card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:13px 14px}
.row-top{display:flex;align-items:flex-start;gap:11px}
.row-avatar{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;font-size:12.5px;font-weight:800;color:#fff;flex-shrink:0}
.row-body{flex:1;min-width:0}
.row-name{font-size:14px;font-weight:800;color:var(--ink)}
.row-meta{font-size:12px;color:var(--muted);margin-top:2px;font-weight:600}

/* Buttons */
.btn{height:34px;border-radius:10px;padding:0 13px;font-size:12.5px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;gap:6px}
.btn-coral{background:var(--coral);color:#fff}
.btn-outline{background:#fff;border:1.4px solid var(--line);color:var(--ink)}
.btn-ghost{background:var(--gray-bg);color:var(--ink)}
.iconround{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;flex-shrink:0}
.iconround svg{width:17px;height:17px}
.iconround.wa{background:var(--success-bg);color:var(--success)}

/* Segmented control */
.segmented{display:flex;background:var(--gray-bg);border-radius:12px;padding:3px;margin:14px 16px 4px}
.seg{flex:1;text-align:center;padding:9px 0;border-radius:9px;font-size:12.5px;font-weight:800;color:var(--muted)}
.seg.active{background:#fff;color:var(--ink);box-shadow:0 1px 2px rgba(16,24,40,.08)}

/* Search + filter chips */
.search{display:flex;align-items:center;gap:9px;background:var(--card);border:1px solid var(--line);border-radius:12px;padding:10px 13px;margin:12px 16px 8px}
.search svg{width:17px;height:17px;color:var(--faint);flex-shrink:0}
.search span{font-size:13.5px;color:var(--faint);font-weight:600}
.chips{display:flex;gap:8px;padding:2px 16px 12px;overflow-x:auto}
.chip{flex-shrink:0;padding:7px 13px;border-radius:100px;font-size:12.5px;font-weight:700;background:var(--card);border:1px solid var(--line);color:var(--muted);white-space:nowrap}
.chip.active{background:var(--navy);color:#fff;border-color:var(--navy)}

/* Bottom nav */
.bottomnav{position:fixed;left:0;right:0;bottom:0;z-index:6;display:grid;grid-template-columns:repeat(4,1fr);align-items:stretch;background:#fff;border-top:1px solid var(--line);padding:8px 4px calc(8px + env(safe-area-inset-bottom))}
.navitem{display:flex;flex-direction:column;align-items:center;gap:4px;padding:4px 0;color:#8A94AC}
.navitem svg{width:22px;height:22px}
.navitem .lbl{font-size:10.5px;font-weight:700;letter-spacing:.1px}
.navitem.on{color:var(--coral)}

/* Floating action button */
.fab{position:fixed;right:16px;bottom:82px;width:54px;height:54px;border-radius:50%;background:var(--coral);color:#fff;display:grid;place-items:center;box-shadow:0 12px 20px -6px rgba(255,106,61,.5),0 4px 10px rgba(10,31,61,.14);z-index:7}
.fab svg{width:23px;height:23px}

/* Sticky bottom action bar (detail screens) */
.stickybar{position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid var(--line);padding:12px 16px calc(12px + env(safe-area-inset-bottom));display:flex;gap:10px;z-index:6}
.stickybar .btn{flex:1;height:46px;border-radius:12px;font-size:14px}
</style>
