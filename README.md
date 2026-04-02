# ICT Leerlijn

**Gemaakt door:** Rick Dörr  
**Instelling:** ROC Midden Nederland — ICT-College  
**Rol:** Docent Software Development, MBO-3 en MBO-4

---

## Oorsprong van het project

Dit platform is ontstaan vanuit een concrete behoefte in de dagelijkse lespraktijk. Als docent Software Development bij ROC Midden Nederland geef ik les in Python, JavaScript (Processing/p5.js), Java, Unity 6, Unity 6 OpenXR/VR en twee MBO-rekencursussen (N3 en N4). Het lesmateriaal was versnipperd over Teams, losse PowerPoints, Word-documenten en externe sites als W3Schools — zonder structuur, zonder voortgangsregistratie, en zonder een centrale plek waar studenten altijd terecht konden.

De opdracht aan mezelf was eenvoudig: **bouw een leerplatform dat eruitziet en aanvoelt als Udemy of W3Schools, maar volledig is afgestemd op onze eigen vakken, onze eigen studenten en onze eigen manier van lesgeven.** Het platform moest modulair zijn zodat ik het makkelijk kon uitbreiden, en simpel genoeg dat ik het zonder externe diensten kon hosten en onderhouden.

Uit die behoefte is ICT Leerlijn gegroeid — iteratie voor iteratie, functie voor functie.

---

## Doelstelling

ICT Leerlijn biedt MBO-studenten Software Development één centrale leeromgeving met:

- Gestructureerd lesmateriaal per vak, georganiseerd in secties en pagina's
- Voortgang en gamification (XP, levels, streaks, badges)
- Een docenteninterface om lessen te maken zonder code te schrijven
- Een meldingssysteem waarmee studenten fouten in lesmateriaal kunnen rapporteren

---

## Designstijl

Het platform heeft een bewuste, consistente visuele identiteit die aansluit bij de wereld van softwareontwikkeling.

**Dark mode als standaard** — het donkere GitHub Dark kleurenpalet (`#0d1117` achtergrond, `#e6edf3` tekst) is de standaardmodus. Het voelt vertrouwd voor studenten die dagelijks in code-editors werken. Een schakelknop (🌙 / ☀️) in de topbar wisselt naar een GitHub Light variant.

**Twee lettertypes** — Space Grotesk voor alle interface-tekst: modern, goed leesbaar op scherm, met karakter. JetBrains Mono voor alle code: de standaard in de ontwikkelaarswereld, herkenbaar voor studenten.

**Kleurcodering per vak** — elk vak heeft een eigen accentkleur die consistent terugkomt in de sidebar, de cursuskaarten en de voortgangsbalken:

| Vak | Kleur |
|---|---|
| Python | Blauw `#388bfd` |
| JavaScript | Geel `#e3b341` |
| Java | Oranje `#f78166` |
| Unity 6 | Paars `#bc8cff` |
| Unity 6 OpenXR/VR | Groen `#3fb950` |
| Rekenen MBO | Grijs `#8b949e` |

**Geen frameworks, geen build-stap** — het platform is gebouwd in pure HTML, CSS en JavaScript. Studenten kunnen de broncode inzien en begrijpen. Starten gaat met één commando, hosten vereist geen Node.js, geen npm, geen webpack.

**Componenttypes in lessen** — lesinhoud bestaat uit bouwstenen met een eigen visuele stijl:
- Tekst met markdown-achtige opmaak
- Codeblokken met syntaxkleuring (per taal)
- Info-boxes in drie smaken: 💡 tip (blauw), ⚠️ waarschuwing (oranje), ✅ succes (groen)
- Opdrachtenboxen
- Video-embeds

---

## Vakken in het platform

| Sectie | Cursus | Niveau |
|---|---|---|
| Programmeren | Python | MBO-4 |
| Programmeren | JavaScript (Processing / p5.js) | MBO-4 |
| Programmeren | Java | MBO-4 |
| Game & VR | Unity 6 | MBO-4 |
| Game & VR | Unity 6 OpenXR / VR | MBO-4 |
| Rekenen MBO | Rekenen voor N4 | MBO-4 |
| Rekenen MBO | Rekenen voor N3 | MBO-3 |

---

## Snel starten

```bash
cd ict-leerlijn
python -m http.server 8080
```

Open in de browser:
- `http://localhost:8080` — studentenview
- `http://localhost:8080/admin.html` — lesontwerper (docentenview)

> **Let op:** lesbestanden worden via `fetch()` geladen. Een lokale server is vereist — dubbelklikken op `index.html` werkt niet.

**Via VS Code:** installeer de extensie "Live Server" → rechtermuisknop op `index.html` → "Open with Live Server".

---

## Bestandsstructuur

```
ict-leerlijn/
│
├── index.html              ← Studentenview
├── admin.html              ← Lesontwerper (docentenview)
│
├── components/
│   └── sidebar.html        ← Sidebar HTML-template (losgekoppeld van index)
│
├── css/
│   └── main.css            ← Alle styling, dark + light mode
│
├── js/
│   ├── courses.js          ← ★ Cursusdata — hier pas je als docent aan
│   ├── sidebar.js          ← Sidebar ES-module (fetch, build, sync)
│   └── app.js              ← App-logica: navigatie, paginering, meldingen
│
├── lessons/                ← Lesinhoud als HTML-fragmenten
│   ├── python/
│   │   └── loops.html
│   ├── unity/
│   │   └── scripting.html
│   └── math/
│       └── n4-verhoudingen.html
│
└── database/
    ├── schema.sql          ← Database-schema + seed data
    └── leerlijn.db         ← SQLite database
```

---

## Architectuur in drie lagen

```
courses.js            →  Wat bestaat er? (cursussen, pagina's, bestanden)
lessons/*.html        →  Wat staat erin? (HTML-fragmenten met <section> tags)
js/sidebar.js         →  Sidebar component (eigen ES-module, herbruikbaar)
js/app.js             →  App-logica: navigatie, lessen laden, paginering
components/sidebar.html → Sidebar HTML-template (losgekoppeld van index.html)
```

Een docent die lesmateriaal toevoegt raakt `app.js` of `sidebar.js` nooit aan.

---

## Iteraties — wat is gebouwd en waarom

### Iteratie 1 — Interface & sidebar

Gebaseerd op de Udemy/W3Schools-ervaring: een collapsible sidebar met cursusgroepen, voortgangsbalken, statusindicatoren per les (✓ klaar / ▶ bezig / 🔒 vergrendeld) en type-badges (les / oefening / quiz / project). Rechts een hoofdgebied met het dashboard of de lesweergave.

**Keuze:** geen framework. Pure HTML/CSS/JS zodat het platform eenvoudig te begrijpen, te hosten en te onderhouden is.

---

### Iteratie 2 — Bestandsstructuur

Scheiding van data (`courses.js`), logica (`app.js`) en content (`lessons/*.html`). Een docent die een les toevoegt, maakt alleen een HTML-bestand en voegt één entry toe in `courses.js`.

---

### Iteratie 3 — Database schema

SQLite database met 5 tabellen in een vaste hiërarchie:

```
subject → course → page → section → component
```

| Tabel | Rol |
|---|---|
| `subject` | Programmeren, Game & VR, Rekenen MBO |
| `course` | Python, Unity 6, N4 — met icon en kleur |
| `page` | Een les-eenheid met type (theorie/oefening/quiz/project) en XP |
| `section` | Inhoudelijk blok met naam, splitpunt voor paginering |
| `component` | Kleinste bouwsteen: tekst, code, video, callout, opdracht |

Het `meta`-veld op `component` is JSON voor type-specifieke instellingen (taal bij code, stijl bij callout, antwoordopties bij quiz).

---

### Iteratie 4 — Sectiepaginering

Lessen met veel content worden automatisch gesplitst in "slides". Na het laden worden alle secties off-screen gerenderd om hun pixelhoogte te meten. Secties worden greedy gegroepeerd: zodra de opgetelde hoogte `1.5 × window.innerHeight` overschrijdt, begint een nieuwe slide.

Bij één slide: geen navigatiechrome. Bij meerdere slides: een dunne voortgangsbalk bovenaan en de navigatiebalk onderaan.

**Waarom pixelhoogte:** schermformaten verschillen te veel voor een vaste regel op basis van tekens of regels.

**Contentstructuur:** lesbestanden gebruiken `<section data-title="Naam">` als expliciete splitpunten. Zonder die tags splitst de parser op `<h3>`.

---

### Iteratie 5 — Dark / light thema

De 🌙 / ☀️ schakelknop in de topbar wisselt tussen dark en light mode. Implementatie: `data-theme="light"` op het `<html>`-element schakelt een volledige set CSS-variabele-overrides in. Geen JavaScript voor kleurlogica — alles via CSS. De keuze wordt opgeslagen in `localStorage`.

---

### Iteratie 6 — Meldingensysteem

Studenten vinden fouten. Dat is goed. Ze moeten dat makkelijk kunnen melden.

Bij hover op een sectie verschijnt een subtiel ⚑ vlagje rechts naast de sectietitel. Klikken opent een compact modal met vier meldingstypen:

- ✗ Fout in de inhoud
- ? Onduidelijke uitleg
- ! Probleem met de opdracht
- … Iets anders

Na het invullen van een optionele toelichting en versturen: toast-bevestiging onderin, de vlag kleurt oranje als herinnering.

In het rechterpaneel bij elke les staat een "Meldingen" kaart voor de docent. Per melding: type, locatie (cursus › les › sectie), datum en toelichting. Een "Opgelost ✓" knop archiveert de melding.

**Opslag:** `localStorage` onder `ict-reports`. Geen server nodig in de huidige fase.

---

### Iteratie 7 — Lesontwerper (admin.html)

Een aparte pagina voor docenten om lessen te ontwerpen zonder code te schrijven.

**Twee kolommen:**
- Links: structuurpaneel met de volledige boom van vakken, cursussen en pagina's. Per cursus een "＋ Les toevoegen" knop.
- Rechts: de editor met paginametadata (titel, type, XP) en secties met componenten.

**Componenttypen in de editor:**

| Type | Invoer |
|---|---|
| Tekst | Vrije textarea |
| Code | Code-textarea + taalkeuzelijst + optionele bestandsnaam |
| Tip/Waarschuwing | Stijlknop (💡/⚠️/✅) + titel + tekst |
| Video | URL-veld + optionele ondertitel |
| Opdracht | Label + beschrijving |

Secties en componenten zijn te herordenen met ↑↓-knoppen en te verwijderen. Alles slaat automatisch op in `localStorage` met een debounce van 800ms. De "← Terug naar studentenview" knop navigeert terug naar `index.html`.

---

### Iteratie 8 — Sidebar als losgekoppelde module

De sidebar is geëxtraheerd naar een eigen ES-module zodat hij herbruikbaar is en niet verstrengeld met de app-logica.

**Drie onderdelen:**

- `components/sidebar.html` — de HTML-template (merk, gebruikersrij, nav-placeholder). Één plek voor alle sidebar-opmaak.
- `js/sidebar.js` — ES-module met vier geëxporteerde functies:
  - `initSidebar(mountId, onLessonClick)` — fetcht de template, injecteert hem in het DOM, bouwt de navigatieboom. `async` zodat de app wacht tot de sidebar klaar is.
  - `syncSidebarActive(courseId, lessonId)` — markeert de actieve les en opent de juiste cursusgroep.
  - `toggleGroup(id)` — vouwt een cursusgroep open of dicht.
  - `updateSidebarUser(student)` — vult naam, niveau en XP-balk in.
- `index.html` — de `<aside>` is vervangen door `<div id="sidebar-mount"></div>`. Het script-tag is `type="module"` zodat de ES-import werkt.

`app.js` importeert de functies en roept `await initSidebar(...)` als eerste aan in `DOMContentLoaded`. Als `admin.html` later dezelfde sidebar moet gebruiken is dat één import en één aanroep.

---

### Iteratie 9 — Navigatiestijl van de sectiepaginering

De vorige/volgende-knoppen en dot-indicators zijn volledig herontworden zodat ze passen bij de dark en light thema's en intuïtiever zijn in gebruik.

**Layout:**
- Bovenaan: alleen de dunne voortgangsbalk (breedte = percentage voltooide slides).
- Onderaan, gecentreerd: één navigatiebalk met `←` · dots · paginatelling · `→`.

**Pijlknoppen:** cirkelvormig (36×36px), opgebouwd uit het thema — `var(--bg2)` achtergrond, `var(--border)` rand, subtiele kleur. De vorige-pijl is neutraal. De volgende-pijl heeft een blauwe rand en blauw icoon dat bij hover solide blauw wordt. Op de laatste slide vervangt een groene vinkjesknop de volgende-pijl.

**Dots:** iets groter (8px), met een pill-animatie: de actieve dot rekt uit naar 22px breedte met `border-radius: 4px` in plaats van alleen van kleur te wisselen. Elke dot heeft de sectienaam als tooltip en is klikbaar om direct naar die sectie te springen.

**Paginatelling:** direct onder de dots, gecentreerd, in monospace muted tekst.

**Thema-consistentie:** alle kleuren zijn gedefinieerd via CSS-variabelen. De light-mode overrides staan in het bestaande `[data-theme="light"]` blok, zodat het wisselen van thema ook de navigatie correct omschakelt.

---

## Een les toevoegen

### Via de lesontwerper (aanbevolen)

1. Open `admin.html`
2. Klik "＋ Les toevoegen" naast de gewenste cursus
3. Vul titel, type en XP in
4. Voeg secties en componenten toe
5. Kopieer de gegenereerde HTML uit de browser-console (of bouw een exportfunctie later)
6. Sla op als `lessons/<cursus>/<naam>.html`
7. Voeg de entry toe in `courses.js`

### Handmatig

**`js/courses.js`:**

```javascript
{
  id:     "functies",
  title:  "Functies en parameters",
  type:   "exercise",   // theory | exercise | quiz | project
  status: "locked",     // done | active | locked
  xp:     70,
  file:   "lessons/python/functies.html"
}
```

**`lessons/python/functies.html`:**

```html
<section data-title="Introductie">
  <p class="lesson-text">Functies zijn herbruikbare blokken code.</p>
</section>

<section data-title="Definitie">
  <div class="code-block"><span class="kw">def</span> <span class="fn">groet</span>(naam):
    <span class="fn">print</span>(<span class="st">f"Hallo, {naam}!"</span>)</div>
</section>

<section data-title="Opdrachten">
  <div class="exercise-box">
    <h4>🎯 Opdracht 1</h4>
    <p class="lesson-text">Schrijf een functie die twee getallen optelt.</p>
  </div>
</section>
```

---

## CSS-componenten

| Element | Klasse | Gebruik |
|---|---|---|
| Tekst | `.lesson-text` | Lopende tekst |
| Codeblok | `.code-block` | Monospace, donkere achtergrond |
| Tip-box | `.info-box .info-box-blue` | Tip (ook: `-green`, `-orange`) |
| Box-titel | `.info-box-title` | Titel in een info-box |
| Opdracht | `.exercise-box` | Opdrachtenbox |
| Inline code | `<code>` | Inline code |

**Syntaxkleuren:**

```html
<span class="kw">for</span>            <!-- keyword -->
<span class="fn">print</span>         <!-- functienaam -->
<span class="st">"tekst"</span>       <!-- string -->
<span class="nm">42</span>            <!-- getal -->
<span class="cm"># commentaar</span>  <!-- commentaar -->
<span class="cl">MijnKlasse</span>    <!-- klassenaam -->
```

---

## Technische keuzes

| Keuze | Wat | Waarom |
|---|---|---|
| Geen framework | Vanilla HTML/CSS/JS | Geen build-stap, leesbaar voor studenten, makkelijk te hosten |
| ES modules | `import`/`export` in `sidebar.js` | Losse verantwoordelijkheden, herbruikbare componenten |
| SQLite | Database | Geen server nodig, één bestand, standaard SQL |
| CSS-variabelen | Themasysteem | Dark/light zonder JavaScript kleurlogica |
| `<section>` tags | Paginering | Expliciete splitpunten, fallback op `<h3>` |
| Pixelhoogte meting | Slide-groepering | Schermformaatonafhankelijk, accurater dan tekentelling |
| Cirkelpijlen + pill-dots | Sectienavigatie | Compacter dan tekst-knoppen, thema-neutraal, intuïtief |
| `localStorage` | Meldingen & admin | Geen server nodig in de huidige fase |
| Aparte `admin.html` | Docentenview | Geen code-menging met studentenview |
| `components/sidebar.html` | Sidebar template | Losgekoppeld van `index.html`, eenvoudig te hergebruiken |
| Space Grotesk | UI-lettertype | Modern, leesbaar, karakter zonder cliché te zijn |
| JetBrains Mono | Code | Standaard in de ontwikkelwereld |
| GitHub Dark/Light | Kleurpaletten | Herkenbaar, professioneel, consistent met code-editors |

---

## Backlog

| Prioriteit | Feature |
|---|---|
| Hoog | Voortgang opslaan per student (localStorage of Supabase) |
| Hoog | Studentaccounts en inloggen |
| Hoog | Werkend quizsysteem met score en directe feedback |
| Middel | Meldingen naar een server sturen |
| Middel | Badges op basis van mijlpalen |
| Middel | Zoekfunctie over alle lessen |
| Laag | Video-embed als werkend component |
| Laag | Voortgang exporteren als PDF |
| Laag | Mobiele weergave verfijnen |
