// Film screens: the current iOS app rebuilt at 1.5x points, on top of the MA kit in index.html.
// Layouts follow the SwiftUI views (HomeView, MoodSelectionView, PreferenceView,
// PreferenceLoadingView, ResultView, NearbyView, HalalVerificationSection).
(function () {
  const MA = window.MA;
  const I = (k, extra = "") => MA.icon(k, extra);
  const P = (d, extra = "") => `<svg class="ma-ico" viewBox="0 0 24 24" ${extra}><path d="${d}"/></svg>`;
  const ICON = {
    dice: "M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1zM8.5 8.5h.01M15.5 8.5h.01M12 12h.01M8.5 15.5h.01M15.5 15.5h.01",
    sliders: "M4 7h10M18 7h2M4 17h4M12 17h8M16 5v4M10 15v4",
    stack: "M7 4h12v14M4 7h12v13H4z",
    fork: "M7 3v8M5 3v5a2 2 0 0 0 4 0V3M7 11v10M16 3c-2 1.5-2.5 4-2.5 6.5V13h2.5v8",
    upright: "M7 17L17 7M8 7h9v9",
    back: "M15 5l-7 7 7 7",
    arrow: "M5 12h14M13 6l6 6-6 6",
    seal: "M12 2.5l2.2 1.6 2.7-.2.9 2.6 2.3 1.4-.6 2.6 1 2.5-2 1.8-.4 2.7-2.7.4-1.8 2-2.5-1-2.6.6-1.4-2.3-2.6-.9.2-2.7L2.5 12l1.6-2.2-.2-2.7 2.6-.9 1.4-2.3 2.6.6zM8.5 12.2l2.4 2.4 4.6-5",
    thumb: "M7 11v9H4v-9zM7 11l4-7c1.5 0 2.5 1 2.5 2.5V9h5a2 2 0 0 1 2 2.3l-1.2 6.5A2.5 2.5 0 0 1 16.8 20H7",
    heart: "M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z",
    cash: "M3 7h18v10H3zM12 9.5a2.5 2.5 0 1 0 0 5a2.5 2.5 0 1 0 0-5",
    pin: "M12 21s-6.5-6.2-6.5-11a6.5 6.5 0 0 1 13 0c0 4.8-6.5 11-6.5 11zM12 7.5a2.5 2.5 0 1 0 0 5a2.5 2.5 0 1 0 0-5",
    reroll: "M4 12a8 8 0 0 1 14-5.3M20 4v4h-4M20 12a8 8 0 0 1-14 5.3M4 20v-4h4",
    down: "M17 13V4h3v9zM17 13l-4 7c-1.5 0-2.5-1-2.5-2.5V15h-5a2 2 0 0 1-2-2.3l1.2-6.5A2.5 2.5 0 0 1 7.2 4H17",
    check: "M6 12.5l4 4 8-9",
    warn: "M12 3l10 18H2zM12 10v5M12 18v.01",
    x: "M6 6l12 12M18 6L6 18",
    loc: "M20 4L4 11l7 2 2 7z",
    search: "M10.5 4a6.5 6.5 0 1 0 0 13a6.5 6.5 0 1 0 0-13M15.5 15.5L20 20",
    question: "M9.2 9a3 3 0 1 1 4.3 2.7c-.9.5-1.5 1.2-1.5 2.3M12 17.5v.01",
    up: "M6 15l6-6 6 6",
    dots: "M5 12h.01M12 12h.01M19 12h.01",
  };
  const ic = (k, s = 30, extra = "") => P(ICON[k], `style="width:${s}px;height:${s}px;${extra}"`);
  const STAR = (s = 22, c = "#f4b942") => `<svg viewBox="0 0 24 24" style="width:${s}px;height:${s}px;fill:${c}"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6-4.9-4.6 6.6-.8z"/></svg>`;
  const INK = "#29201d", MUTED = "#7a6f63", RED = "#e94b35", PANDAN = "#4f7a57", HAIR = "#ecdfc6", SURF = "#fffbf3", CREAM = "#fff4dd";

  const status = (dark) => `<div class="ma-status" style="${dark ? "color:#fff" : ""}"><span>12:30</span><span style="display:flex;gap:10px;align-items:center">
      <svg viewBox="0 0 34 22"><rect x="0" y="14" width="6" height="8" rx="1.5" fill="currentColor"/><rect x="9" y="10" width="6" height="12" rx="1.5" fill="currentColor"/><rect x="18" y="5" width="6" height="17" rx="1.5" fill="currentColor"/><rect x="27" y="0" width="6" height="22" rx="1.5" fill="currentColor"/></svg>
      <svg viewBox="0 0 46 22"><rect x="1" y="1" width="38" height="20" rx="6" fill="none" stroke="currentColor" stroke-opacity=".45" stroke-width="2"/><rect x="4" y="4" width="30" height="14" rx="3.5" fill="currentColor"/><rect x="41" y="7" width="3" height="8" rx="1.5" fill="currentColor" fill-opacity=".45"/></svg>
    </span></div>`;
  const circle = (inner, size, bg, color) => `<span style="width:${size}px;height:${size}px;flex:none;border-radius:50%;background:${bg};color:${color};display:grid;place-items:center">${inner}</span>`;
  const surfaceCard = `background:${SURF};border:1.5px solid ${HAIR};border-radius:36px;`;
  const tabs = (on) => `<div class="ma-abs ma-tabs">
      ${[["Decide", "sparkle"], ["Nearby", "map"], ["Community", "people"]].map(([l, k]) => `<div class="${l === on ? "on" : ""}">${I(k)}${l}</div>`).join("")}
    </div>`;

  // ---------------------------------------------------------------- Decide
  MA.home = function (p) {
    const card = (id, top, h, bg, border, iconBg, iconColor, icon, title, sub, subColor, chevron) => `
      <div class="ma-abs" id="${id}" style="left:30px;right:30px;top:${top}px;height:${h}px;border-radius:36px;background:${bg};${border};display:flex;align-items:center;gap:22px;padding:0 30px">
        ${circle(ic(icon, 34), 78, iconBg, iconColor)}
        <span style="flex:1"><b style="display:block;font-size:30px;font-weight:700;letter-spacing:-0.02em">${title}</b><span style="display:block;margin-top:4px;font-size:21px;color:${subColor}">${sub}</span></span>
        ${P("M9 5l7 7-7 7", `style="width:26px;height:26px;color:${chevron}"`)}
      </div>`;
    const recent = (name, detail) => `<div style="display:flex;align-items:center;gap:18px;height:96px;padding:0 24px">
        ${circle(ic("fork", 26), 54, CREAM, INK)}
        <span style="flex:1"><b style="display:block;font-size:23px;font-weight:600">${name}</b><span style="font-size:18px;color:${MUTED}">${detail}</span></span>${ic("upright", 24, `color:${MUTED}`)}
      </div>`;
    return `<div class="ma-scr" id="${p}-home">${status()}
      <p class="ma-abs" style="top:104px;left:32px;font-size:19px;font-weight:600;color:${MUTED};letter-spacing:0.04em;display:flex;gap:8px;align-items:center">${ic("loc", 20)}JASIN</p>
      <p class="ma-abs" style="top:134px;left:30px;font-size:52px;font-weight:800;letter-spacing:-0.04em">Lunch?</p>
      <span class="ma-abs" style="top:124px;right:30px">${circle(I("gear", 'style="width:34px;height:34px"'), 66, "rgb(41 32 29 / 0.06)", INK)}</span>
      ${card(`${p}-quick`, 240, 150, RED, "box-shadow:0 14px 30px rgb(233 75 53 / 0.3)", "rgb(255 255 255 / 0.18)", "#fff", "dice", "Pick for me", "Anything · ~RM20 · 10 min", "rgb(255 255 255 / 0.85)", "rgb(255 255 255 / 0.8)").replace(`background:${RED};`, `background:${RED};color:#fff;`)}
      ${card(`${p}-craving`, 420, 130, SURF, `border:1.5px solid ${HAIR}`, CREAM, INK, "sliders", "I know what I want", "Craving, budget, distance", MUTED, MUTED)}
      ${card(`${p}-saved`, 578, 130, SURF, `border:1.5px solid ${HAIR}`, CREAM, RED, "stack", "Pick from my saved", "Shuffle your 20 saved places", MUTED, MUTED)}
      <p class="ma-abs" style="top:744px;left:34px;font-size:19px;font-weight:600;color:${MUTED}">Recent</p>
      <div class="ma-abs" style="left:30px;right:30px;top:778px;${surfaceCard}overflow:hidden">
        ${recent("Kopi Lima Corner", "Coffee Shop · ≈ RM10/person · Yesterday")}
        <i style="display:block;height:1.5px;margin-left:96px;background:${HAIR}"></i>
        ${recent("Warung Kak Ros", "Malaysian · ≈ RM12/person · Mon")}
      </div>
      <div class="ma-abs" style="left:30px;right:30px;top:990px;height:80px;border-radius:24px;background:${SURF};border:1.5px solid ${HAIR};display:flex;align-items:center;gap:16px;padding:0 22px">
        ${P(ICON.question, `style="width:32px;height:32px;color:${RED};stroke-width:2.4"`)}
        <span style="flex:1"><b style="display:block;font-size:20px;font-weight:600">New to MakanApa?</b><span style="font-size:17px;color:${MUTED}">See how picking works.</span></span>
      </div>
      ${tabs("Decide")}</div>`;
  };

  // Progress header shared by the three steps.
  function stepHeader(step, done) {
    const labels = ["Mood", "Budget", "Distance"];
    return `<div class="ma-abs" style="top:96px;left:30px;right:30px;height:66px;display:flex;align-items:center;justify-content:space-between">
        ${circle(ic("back", 28), 66, "rgb(41 32 29 / 0.06)", INK)}
        <span class="ma-brand" style="font-size:30px">Makan<b>Apa?</b></span><span style="width:66px"></span>
      </div>
      <div class="ma-abs" style="top:180px;left:30px;right:30px;display:flex;gap:14px">
        ${labels.map((l, i) => `<div style="flex:1;font-size:18px;${i + 1 === step ? `color:${INK};font-weight:600` : i < step - 1 ? `color:${RED}` : `color:${MUTED}`}"><i style="display:block;height:4.5px;margin-bottom:10px;border-radius:3px;background:${i < step ? RED : "rgb(41 32 29 / 0.1)"}"></i>${i < step - 1 ? done[i] : l}</div>`).join("")}
      </div>`;
  }
  function footer(p, id, cta, hint, enabled = true) {
    return `<div class="ma-abs" style="left:0;right:0;top:1060px;bottom:0;background:${CREAM}">
        <div class="ma-abs" id="${id}" style="left:36px;right:36px;top:30px;height:84px;border-radius:27px;display:flex;align-items:center;justify-content:space-between;padding:0 34px;font-size:26px;font-weight:600;${enabled ? `background:${RED};color:#fff` : "background:rgb(41 32 29 / 0.08);color:rgb(41 32 29 / 0.35)"}">${cta}${ic("arrow", 30)}</div>
        <p class="ma-abs ma-center" style="top:132px;font-size:18px;color:${MUTED}">${hint}</p>
      </div>`;
  }
  function choiceRow(top, art, title, sub, selId, selected) {
    return `<div class="ma-abs" style="left:36px;right:36px;top:${top}px;height:132px;border-radius:33px;background:rgb(255 255 255 / 0.72);display:flex;align-items:center;gap:22px;padding:0 27px">
        ${art}
        <span style="flex:1"><b style="display:block;font-size:30px;font-weight:700;letter-spacing:-0.02em">${title}</b><span style="font-size:20px;color:${MUTED}">${sub}</span></span>
        <span style="position:relative;width:38px;height:38px"><i style="position:absolute;inset:0;border-radius:50%;border:2.5px solid rgb(41 32 29 / 0.25)"></i>
          <i id="${selId}-on" style="position:absolute;inset:-2px;border-radius:50%;background:${RED};color:#fff;display:grid;place-items:center;opacity:${selected ? 1 : 0}">${ic("check", 24, "stroke-width:3")}</i></span>
        <i id="${selId}" style="position:absolute;inset:0;border-radius:33px;border:2.5px solid ${RED};background:rgb(233 75 53 / 0.07);opacity:${selected ? 1 : 0}"></i>
      </div>`;
  }

  // Moods: CC0 photos where the app's photo is CC0; the CC BY-SA ones become plain warm tiles.
  const MOODS = [
    ["Nasi Kandar", "Kandar power", "tile:#d99a52"],
    ["Ayam Gepuk", "Smashed, spicy, delicious", "tile:#cf7a45"],
    ["Nasi Padang", "Rendang, gulai, the works", "tile:#b8693e"],
    ["Mee Goreng", "Wok hei, always hits", "assets/photos/MeeGoreng.jpg"],
    ["Nasi Lemak", "Anytime, anywhere", "assets/photos/NasiLemak.jpg"],
    ["Char Kuey Teow", "Smoky and satisfying", "tile:#9c6a43"],
    ["Banana Leaf Rice", "Drowned in curry, no regrets", "tile:#7f9a5b"],
    ["Dim Sum", "Small plates, big satisfaction", "assets/photos/DimSum.jpg"],
    ["Healthy", "Something lighter", "assets/photos/Light.svg"],
    ["Quick", "Fast & convenient", "assets/photos/Quick.svg"],
  ];
  MA.mood = function (p) {
    const thumb = (src) => src.startsWith("tile:")
      ? `<span style="width:66px;height:66px;flex:none;border-radius:18px;background:radial-gradient(circle at 35% 30%, ${src.slice(5)}cc, ${src.slice(5)})"></span>`
      : `<img src="${src}" style="width:66px;height:66px;flex:none;border-radius:18px;object-fit:cover;background:#fff" alt="" />`;
    const rows = MOODS.map(([l, s, src], i) => `<div class="ma-abs" style="left:36px;right:36px;top:${600 + i * 108}px;height:96px;border-radius:27px;background:rgb(255 255 255 / 0.72);display:flex;align-items:center;gap:20px;padding:0 15px">
        ${thumb(src)}<span style="flex:1"><b style="display:block;font-size:25px;font-weight:700">${l}</b><span style="font-size:18px;color:${MUTED}">${s}</span></span>
        <span style="position:relative;width:34px;height:34px;margin-right:12px"><i style="position:absolute;inset:0;border-radius:50%;border:2.5px solid rgb(41 32 29 / 0.25)"></i><i id="${p}-mood-on-${i}" style="position:absolute;inset:-2px;border-radius:50%;background:${RED};color:#fff;display:grid;place-items:center;opacity:0">${ic("check", 22, "stroke-width:3")}</i></span>
        <i id="${p}-mood-sel-${i}" style="position:absolute;inset:0;border-radius:27px;border:2.3px solid ${RED};background:rgb(233 75 53 / 0.07);opacity:0"></i>
      </div>`).join("");
    return `<div class="ma-scr" id="${p}-mood">
      <div class="ma-abs" id="${p}-mood-list" style="left:0;right:0;top:0;height:1400px">
        <p class="ma-abs" style="top:236px;left:36px;font-size:54px;font-weight:800;letter-spacing:-0.045em">What mood today?</p>
        <p class="ma-abs" style="top:304px;left:38px;font-size:21px;color:${MUTED}">What are you craving?</p>
        <div class="ma-abs" style="left:36px;right:36px;top:356px;height:78px;border-radius:27px;background:rgb(255 255 255 / 0.72);display:flex;align-items:center;gap:14px;padding:0 24px;font-size:21px;color:${MUTED}">${ic("search", 26, "opacity:.45")}Tell me what you're craving...</div>
        <div class="ma-abs" style="left:36px;right:36px;top:452px;height:96px;border-radius:27px;background:rgb(255 255 255 / 0.72);display:flex;align-items:center;gap:20px;padding:0 15px">
          ${circle(ic("dice", 30), 66, "rgb(79 122 87 / 0.15)", PANDAN).replace("border-radius:50%", "border-radius:18px")}<span style="flex:1"><b style="display:block;font-size:25px;font-weight:700">Anything</b><span style="font-size:18px;color:${MUTED}">Good food can surprise me.</span></span>
        </div>
        <p class="ma-abs" style="top:566px;left:40px;font-size:18px;font-weight:600;color:rgb(41 32 29 / 0.6)">Or pick one</p>
        ${rows}
      </div>
      <div class="ma-abs" style="left:0;right:0;top:0;height:228px;background:linear-gradient(${CREAM} 88%, rgb(255 244 221 / 0))">${status()}${stepHeader(1, [])}</div>
      ${footer(p, `${p}-mood-go`, "Continue", "Pick a mood, or leave it to us", false)}
      <div class="ma-abs" id="${p}-mood-go-on" style="left:36px;right:36px;top:1090px;height:84px;border-radius:27px;display:flex;align-items:center;justify-content:space-between;padding:0 34px;font-size:26px;font-weight:600;background:${RED};color:#fff;opacity:0">Continue${ic("arrow", 30)}</div>
    </div>`;
  };
  MA.budget = function (p) {
    const rows = [["Budget_Save", "~RM10", "Save a bit"], ["Budget_Normal", "~RM20", "Normal"], ["Budget_Treat", "~RM35+", "Treat myself"]]
      .map(([a, t, s], i) => choiceRow(380 + i * 150, `<img src="assets/ui/${a}.svg" style="width:84px;height:84px" alt="" />`, t, s, `${p}-budget-sel-${i}`, i === 1)).join("");
    return `<div class="ma-scr" id="${p}-budget">${status()}${stepHeader(2, ["Nasi Lemak"])}
      <p class="ma-abs" style="top:236px;left:36px;font-size:54px;font-weight:800;letter-spacing:-0.045em">What's the budget?</p>
      <p class="ma-abs" style="top:304px;left:38px;font-size:21px;color:${MUTED}">Per person. Good food at every budget.</p>
      ${rows}
      ${choiceRow(830, circle(ic("dice", 34), 84, "rgb(79 122 87 / 0.15)", PANDAN), "Anything", "No budget limit. Just make it good.", `${p}-budget-sel-3`, false)}
      ${footer(p, `${p}-budget-go`, "Continue", "One last thing, how far?")}
    </div>`;
  };
  MA.distance = function (p) {
    const rows = [["Distance_Near", "Within 1 km", "Close by"], ["Distance_Walk", "Within 2 km", "Short trip"], ["Distance_Car", "Within 5 km", "Worth the trip"]]
      .map(([a, t, s], i) => choiceRow(380 + i * 150, `<img src="assets/ui/${a}.svg" style="width:84px;height:84px" alt="" />`, t, s, `${p}-dist-sel-${i}`, i === 1)).join("");
    return `<div class="ma-scr" id="${p}-distance">${status()}${stepHeader(3, ["Nasi Lemak", "~RM20"])}
      <p class="ma-abs" style="top:236px;left:36px;font-size:54px;font-weight:800;letter-spacing:-0.045em">How far will you go?</p>
      <p class="ma-abs" style="top:304px;left:38px;right:36px;font-size:21px;color:${MUTED}">Stay nearby or go a little further for good food.</p>
      ${rows}
      <p class="ma-abs" style="top:850px;left:40px;font-size:19px;color:${MUTED};display:flex;gap:10px;align-items:center">${ic("loc", 22)}Distance from your current location.</p>
      ${footer(p, `${p}-find`, "Find my food", "We'll take it from here.")}
    </div>`;
  };
  MA.loading = function (p, mood) {
    const row = (k, t) => `<div style="display:flex;gap:14px;align-items:center;font-size:21px">${ic(k, 24, `color:${RED}`)}${t}</div>`;
    return `<div class="ma-scr" id="${p}-loading">${status()}
      <p class="ma-abs ma-center" style="top:130px"><span class="ma-brand" style="font-size:30px">Makan<b>Apa?</b></span></p>
      <span class="ma-abs" style="left:225px;top:420px;width:140px;height:18px;border-radius:50%;background:rgb(41 32 29 / 0.12)"></span>
      <img class="ma-abs" id="${p}-load-mascot" src="assets/mascot/thinking.svg" style="left:169px;top:186px;width:252px;height:252px" alt="" />
      <p class="ma-abs ma-center" style="top:470px;font-size:48px;font-weight:800;letter-spacing:-0.035em;line-height:1.1">Finding your<br/>next meal.</p>
      <p class="ma-abs ma-center" style="top:600px;font-size:21px;line-height:1.4;color:${MUTED}">Hang tight. We're looking for a spot<br/>that fits your craving.</p>
      <div class="ma-abs" style="left:75px;right:75px;top:700px;padding:30px;border-radius:33px;background:rgb(255 255 255 / 0.55);border:1.5px solid rgb(41 32 29 / 0.07);display:flex;flex-direction:column;gap:16px">
        <b style="font-size:24px">Your kind of food</b><i style="height:1.5px;background:${HAIR}"></i>
        ${row("heart", mood)}${row("cash", "~RM20 per person")}${row("pin", "Within 2 km")}
      </div>
      <p class="ma-abs ma-center" style="top:1030px;font-size:19px;color:${MUTED};display:flex;justify-content:center;gap:12px;align-items:center">
        <span id="${p}-spin" style="width:24px;height:24px;border-radius:50%;border:3px solid rgb(79 122 87 / 0.25);border-top-color:${PANDAN}"></span>Looking for nearby spots…</p>
    </div>`;
  };
  MA.result = function (p, { photo, name, details, chips }) {
    return `<div class="ma-scr" id="${p}-result">
      <div class="ma-abs" id="${p}-hero" style="left:0;right:0;top:0;height:510px;overflow:hidden">
        <img src="${photo}" style="width:100%;height:100%;object-fit:cover" alt="" />
        <i style="position:absolute;left:0;right:0;top:0;height:180px;background:linear-gradient(rgb(255 244 221 / 0.75), rgb(255 244 221 / 0))"></i>
        <span style="position:absolute;right:26px;bottom:24px;display:flex;gap:6px;align-items:center;padding:8px 16px;border-radius:22px;background:${SURF};font-size:20px;font-weight:600">${STAR(20)}4.6</span>
      </div>
      ${status()}
      <div class="ma-abs" id="${p}-res-body" style="left:30px;right:30px;top:540px;display:flex;flex-direction:column;gap:18px">
        <span style="display:flex;gap:8px;align-items:center;font-size:20px;font-weight:600;color:${RED}">${circle(ic("check", 16, "stroke-width:3.5"), 26, RED, "#fff")}Picked for you</span>
        <b style="font-size:45px;font-weight:800;letter-spacing:-0.035em;line-height:1.05">${name}</b>
        <span style="font-size:21px;color:${MUTED};line-height:1.35">${details}</span>
        <span style="align-self:flex-start;display:flex;gap:8px;align-items:center;padding:10px 18px;border-radius:24px;background:rgb(79 122 87 / 0.16);color:${PANDAN};font-size:19px;font-weight:600">${ic("seal", 24)}Halal certified</span>
        <span style="margin-top:8px;font-size:19px;font-weight:600;color:${MUTED}">Why this?</span>
        <span style="display:flex;gap:10px">${chips.map((c, i) => `<i id="${p}-chip-${i}" style="font-style:normal;padding:10px 20px;border-radius:24px;background:${SURF};border:1.5px solid ${HAIR};font-size:19px">${c}</i>`).join("")}</span>
      </div>
      <div class="ma-abs" style="left:0;right:0;top:1140px;bottom:0;background:${CREAM};border-top:1.5px solid ${HAIR}">
        <div class="ma-abs" id="${p}-go" style="left:30px;right:228px;top:22px;height:84px;border-radius:27px;background:${RED};color:#fff;display:grid;place-items:center;font-size:30px;font-weight:800">Let's eat</div>
        <span class="ma-abs" style="right:122px;top:22px">${circle(ic("reroll", 32), 84, SURF, INK).replace("border-radius:50%", `border-radius:50%;border:1.5px solid ${HAIR}`)}</span>
        <span class="ma-abs" style="right:30px;top:22px">${circle(ic("down", 32), 84, SURF, INK).replace("border-radius:50%", `border-radius:50%;border:1.5px solid ${HAIR}`)}</span>
      </div>
    </div>`;
  };

  // ---------------------------------------------------------------- Nearby
  const PINS = [
    // x, y, rating, matches "nasi lemak", non-halal
    [80, 330, "4.6", false, false], [330, 280, "4.4", false, true], [250, 520, "4.7", true, false], [60, 700, "4.5", true, false],
    [390, 640, "4.3", false, false], [150, 860, "4.2", false, true], [420, 470, "4.8", true, false], [300, 780, "4.1", false, false],
  ];
  MA.nearby = function (p) {
    const pins = PINS.map(([x, y, r], i) => `<span class="ma-abs" id="${p}-pin-${i}" style="left:${x}px;top:${y}px;padding:8px 14px;border-radius:20px;background:#fff;box-shadow:0 4px 12px rgb(0 0 0 / 0.18);font-size:18px;font-weight:700;display:flex;gap:5px;align-items:center;transform-origin:50% 100%">${STAR(18)}${r}</span>`).join("");
    const chip = (id, t) => `<span id="${id}" style="flex:none;padding:12px 21px;border-radius:24px;background:#fff;color:${INK};font-size:19px;font-weight:500;box-shadow:0 3px 10px rgb(0 0 0 / 0.12)">${t}</span>`;
    return `<div class="ma-scr" id="${p}-near">
      <div class="ma-abs" style="inset:0">${MA.mapArt(14, { pin: false, w: 590, h: 1278 })}</div>
      ${pins}
      <span class="ma-abs" style="left:279px;top:610px;width:30px;height:30px;border-radius:50%;background:#3b82f6;border:5px solid #fff;box-shadow:0 0 0 14px rgb(59 130 246 / 0.18)"></span>
      <div class="ma-abs" style="left:0;right:0;top:0">${status()}</div>
      <div class="ma-abs" id="${p}-ribbon" style="left:18px;right:96px;top:100px;display:flex;gap:10px;overflow:hidden">
        ${chip(`${p}-halal`, "Hide non-halal")}${chip(`${p}-open`, "Open now")}${chip(`${p}-rm`, "≤ RM20")}${chip(`${p}-star`, "4.5+ ★")}
        <span style="flex:none;padding:12px 21px;border-radius:24px;background:${RED};color:#fff;font-size:19px;font-weight:500">For you</span>
      </div>
      <span class="ma-abs" id="${p}-searchbtn" style="right:18px;top:96px">${circle(ic("search", 26), 60, "#fff", INK).replace("border-radius:50%", "border-radius:50%;box-shadow:0 4px 12px rgb(0 0 0 / 0.15)")}</span>
      <div class="ma-abs" id="${p}-field" style="left:18px;right:18px;top:96px;height:64px;border-radius:32px;background:#fff;box-shadow:0 4px 12px rgb(0 0 0 / 0.15);display:flex;align-items:center;gap:12px;padding:0 22px;font-size:21px;opacity:0">
        ${ic("search", 24, `color:${MUTED}`)}<span style="flex:1;position:relative"><span id="${p}-field-ph" style="color:${MUTED}">Search "nasi lemak"</span><span id="${p}-field-typed" style="position:absolute;left:0;top:0;white-space:nowrap"></span></span>${ic("x", 22, `color:${MUTED}`)}
      </div>
      <div class="ma-abs" id="${p}-notice" style="left:18px;right:18px;top:176px;padding:18px 20px;border-radius:24px;background:#fff;border:1.5px solid #f4b942;display:flex;gap:14px;opacity:0">
        ${ic("warn", 28, "color:#f4b942;flex:none")}
        <span><b style="display:block;font-size:19px">Halal info is community-sourced</b><span style="font-size:16px;line-height:1.35;color:${MUTED}">Halal info may be incomplete or out of date. Always double-check at the restaurant (look for the halal certificate) before you eat.</span></span>
      </div>
      <span class="ma-abs" style="right:18px;top:860px">${circle(P("M12 3l7 17-7-4-7 4z", 'style="width:28px;height:28px;fill:currentColor;stroke:none"'), 66, "#fff", RED).replace("border-radius:50%", "border-radius:50%;box-shadow:0 4px 12px rgb(0 0 0 / 0.15)")}</span>
      <span class="ma-abs" style="right:18px;top:940px">${circle(ic("question", 30, "stroke-width:2.5"), 66, "#fff", INK).replace("border-radius:50%", "border-radius:50%;box-shadow:0 4px 12px rgb(0 0 0 / 0.15)")}</span>
      <div class="ma-abs" style="left:18px;right:18px;top:1030px;display:flex;gap:12px">
        <span style="flex:1;height:64px;border-radius:32px;background:#fff;box-shadow:0 4px 12px rgb(0 0 0 / 0.12);display:flex;align-items:center;gap:8px;padding:0 22px;font-size:19px"><b style="font-weight:600">Around here</b><span style="color:${MUTED}">· 24 places nearby</span></span>
        <span style="height:64px;border-radius:32px;background:${RED};color:#fff;display:grid;place-items:center;padding:0 26px;font-size:21px;box-shadow:0 6px 14px rgb(233 75 53 / 0.35)">Pick for me</span>
      </div>
      ${tabs("Nearby")}
      ${MA.placeSheet(p)}
    </div>`;
  };

  // The place sheet: header, buttons, the halal section, then the photo.
  MA.placeSheet = function (p) {
    return `<div class="ma-abs" id="${p}-sheet" style="left:0;right:0;top:470px;height:1400px;border-radius:34px 34px 0 0;background:#fbf8f3;box-shadow:0 -10px 40px rgb(0 0 0 / 0.18)">
      <i class="ma-abs" style="left:270px;top:12px;width:50px;height:6px;border-radius:3px;background:#d6cdbf"></i>
      <div class="ma-abs" id="${p}-sheet-in" style="left:30px;right:30px;top:40px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
          <span style="display:flex;flex-direction:column;gap:6px">
            <b style="font-size:31px;font-weight:800;letter-spacing:-0.03em">Nasi Lemak Daun Pisang</b>
            <span style="font-size:21px;color:${PANDAN};font-weight:600">Open · closes 11:00 PM</span>
            <span style="font-size:19px;color:${MUTED}">650 m away · Jalan Besar, Jasin</span>
            <span style="font-size:19px;color:${MUTED};display:flex;gap:6px;align-items:center">${STAR(19)}<b style="color:${INK};font-weight:600">4.7</b> · ≈ RM10/person · Malaysian</span>
            <span style="align-self:flex-start;margin-top:6px;display:flex;gap:8px;align-items:center;padding:9px 16px;border-radius:22px;background:rgb(79 122 87 / 0.16);color:${PANDAN};font-size:18px;font-weight:600">${ic("seal", 22)}Halal certified</span>
          </span>
          <span style="display:flex;gap:6px;align-items:center;color:${MUTED}">
            <span id="${p}-heart" style="position:relative;width:54px;height:54px;display:grid;place-items:center">${ic("heart", 34)}<i id="${p}-heart-on" style="position:absolute;inset:0;display:grid;place-items:center;opacity:0">${P(ICON.heart, `style="width:34px;height:34px;fill:${RED};stroke:${RED}"`)}</i></span>${ic("dots", 34, "stroke-width:3.5")}
          </span>
        </div>
        <div style="display:flex;gap:14px;margin-top:26px">
          <span style="flex:1;height:68px;border-radius:34px;background:${RED};color:#fff;display:grid;place-items:center;font-size:21px;font-weight:600">Directions</span>
          <span style="flex:1;height:68px;border-radius:34px;background:rgb(233 75 53 / 0.1);color:${RED};display:grid;place-items:center;font-size:21px;font-weight:600">Eat here</span>
        </div>
        <div id="${p}-halal-sec" style="margin-top:26px;padding:21px;border-radius:27px;background:rgb(41 32 29 / 0.04);display:flex;flex-direction:column;gap:15px">
          <span style="font-size:16px;letter-spacing:0.08em;color:${MUTED}">HALAL</span>
          <div id="${p}-cert" style="display:flex;flex-direction:column;gap:12px;padding:18px;border-radius:21px;background:#fff;border:1.5px solid rgb(79 122 87 / 0.35)">
            <span style="align-self:flex-start;display:flex;gap:8px;align-items:center;padding:9px 16px;border-radius:22px;background:rgb(79 122 87 / 0.16);color:${PANDAN};font-size:19px;font-weight:600">${ic("seal", 24)}Halal certified</span>
            <span style="font-size:18px;color:${MUTED}">Checked against the official registry · 3 Oct 2026</span>
            <span style="font-size:19px;font-weight:600">Certificate valid until 12 Mar 2027</span>
          </div>
          <div id="${p}-vouch" style="display:flex;flex-direction:column;gap:12px">
            <div style="display:flex;align-items:center;gap:14px;padding:16px 18px;border-radius:18px;background:#fff">
              ${ic("thumb", 28, `color:${RED};flex:none`)}<span style="flex:1"><b style="display:block;font-size:19px;font-weight:600">Is this still accurate?</b><span style="font-size:16px;color:${MUTED}">Vouch for it. Our team reviews every vouch</span></span>${P("M9 5l7 7-7 7", `style="width:22px;height:22px;color:${MUTED}"`)}
            </div>
            <div style="padding:16px 18px;border-radius:18px;background:#fff;display:flex;flex-direction:column;gap:8px">
              <span style="align-self:flex-start;padding:4px 10px;border-radius:10px;background:rgb(79 122 87 / 0.12);color:${PANDAN};font-size:13px;font-weight:600;letter-spacing:0.06em">CURRENT EVIDENCE</span>
              <span style="font-size:17px"><b style="font-weight:600">Aina</b> <span style="color:${PANDAN}">· Trusted contributor</span> <span style="color:${MUTED}">· 2 Oct</span></span>
              <i style="font-size:18px;color:${INK}">"Certificate is on the wall by the counter."</i>
            </div>
          </div>
          <span style="font-size:15px;line-height:1.4;color:${MUTED}">Community-sourced: halal info may be incomplete. Always double-check at the restaurant.</span>
        </div>
        <div style="margin-top:26px;height:240px;border-radius:30px;overflow:hidden"><img src="assets/photos/NasiLemak.jpg" style="width:100%;height:100%;object-fit:cover" alt="" /></div>
      </div>
    </div>`;
  };
})();
