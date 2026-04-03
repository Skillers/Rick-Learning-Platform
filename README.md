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

**Geen frameworks, geen build-stap** — het platform is gebouwd in pure HTML, CSS en JavaScript aan de front-end, met PHP en MySQL aan de back-end. Studenten kunnen de broncode inzien en begrijpen. Hosten vereist geen Node.js, geen npm, geen webpack — alleen XAMPP.

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

Het platform vereist XAMPP (Apache + MySQL). Een gewone statische server is niet voldoende omdat de API-endpoints PHP draaien.

1. Zet XAMPP aan (Apache + MySQL)
2. Zet het project in `htdocs/` of gebruik de `DocumentRoot` in Apache
3. Importeer in phpMyAdmin:
   - `database/CreateScriptRickLearningPlatform.sql` — maakt de database en tabellen
   - `database/seed.sql` — vult vakken, cursussen en pagina's
   - `database/seed_sections.sql` — vult demosecties
4. Open in de browser:
   - `http://localhost/` — studentenview
   - `http://localhost/admin.html` — lesontwerper (docentenview)

**Database-instellingen** staan in `config/db.settings.php`. Standaard: host `localhost`, geen wachtwoord.

---

## Bestandsstructuur

```
ict-leerlijn/
│
├── index.html              ← Studentenview
├── admin.html              ← Lesontwerper (docentenview)
│
├── components/
│   └── sidebar.html        ← Sidebar HTML-template
│
├── config/
│   ├── db.settings.php     ← Host, databasenaam, gebruiker, wachtwoord
│   └── db.connection.php   ← PDO-verbinding (gebruikt door alle API-bestanden)
│
├── api/
│   ├── subjects.php        ← GET: alle vakgroepen
│   ├── courses.php         ← GET: alle cursussen met vakgroep
│   ├── pages.php           ← GET: gepubliceerde pagina's met minimaal één sectie
│   └── sections.php        ← GET: alle secties per pagina
│
├── css/
│   └── main.css            ← Alle styling, dark + light mode
│
├── js/
│   ├── sidebar.js          ← Sidebar ES-module (fetch, build, sync, export)
│   └── app.js              ← App-logica: navigatie, paginering, meldingen
│
├── lessons/                ← Lesinhoud als HTML-fragmenten (optioneel)
│   └── ...
│
└── database/
    ├── CreateScriptRickLearningPlatform.sql  ← Schema
    ├── seed.sql                              ← Vakken, cursussen, pagina's
    ├── seed_sections.sql                     ← Demosecties
    ├── alter_cascade_delete.sql              ← FK cascade on delete
    ├── alter_charset_utf8mb4.sql             ← Emoji-ondersteuning
    └── drop_database.sql                     ← Reset
```

---

## Architectuur

```
MySQL DB
  └── api/*.php              PHP-queries, JSON output
        └── js/sidebar.js    Fetcht 4 lagen, bouwt navigatieboom, exporteert data
              └── js/app.js  Navigatie, lesweergave, paginering, weergavewisseling
```

De sidebar laadt data in vier opeenvolgende lagen:

1. **Subjects** — vakgroepskoppen (Programmeren, Game & VR, Rekenen MBO)
2. **Courses** — cursusgroepen per vakgroep met icoon en kleur
3. **Pages** — lesitems per cursus (alleen gepubliceerd én heeft secties)
4. **Sections** — sub-items per pagina, inklapbaar via een pijl

Een docent die lesmateriaal toevoegt, werkt alleen in de database. `app.js` en `sidebar.js` hoeven niet aangepast te worden.

---

## Iteraties — wat is gebouwd en waarom

### Iteratie 1 — Interface & sidebar

Gebaseerd op de Udemy/W3Schools-ervaring: een collapsible sidebar met cursusgroepen, voortgangsbalken, statusindicatoren per les (✓ klaar / ▶ bezig / 🔒 vergrendeld) en type-badges (les / oefening / quiz / project). Rechts een hoofdgebied met het dashboard of de lesweergave.

**Keuze:** geen framework. Pure HTML/CSS/JS zodat het platform eenvoudig te begrijpen, te hosten en te onderhouden is.

---

### Iteratie 2 — Bestandsstructuur

Scheiding van data, logica en content. Lesinhoud als HTML-fragmenten in `lessons/`, app-logica in `app.js`, navigatie in `sidebar.js`.

---

### Iteratie 3 — Database schema

MySQL database met 5 tabellen in een vaste hiërarchie:

```
subject → course → page → section
```

| Tabel | Rol |
|---|---|
| `Subjects` | Programmeren, Game & VR, Rekenen MBO |
| `Courses` | Python, Unity 6, N4 — met icon en kleur (CSS-klassenaam) |
| `Pages` | Een les-eenheid met type (lesson/exercise/quiz/project), volgorde en published-flag |
| `Sections` | Inhoudelijk blok met titel en volgorde binnen een pagina |

Cascade delete is ingesteld: een cursus verwijderen verwijdert ook alle bijbehorende pagina's en secties.

---

### Iteratie 4 — Sectiepaginering

Lessen worden gesplitst in slides: één sectie per slide. De student bladert door secties met vorige/volgende-pijlen. Een dot-indicator toont op welke sectie de student zit.

Bij één sectie: geen navigatiechrome. Bij meerdere: een dunne voortgangsbalk bovenaan en de navigatiebalk onderaan.

**Contentstructuur:** lesbestanden gebruiken `<section data-title="Naam">` als expliciete splitpunten. Zonder die tags splitst de parser op `<h3>`. Als er geen lesbestand is, worden de sectietitels uit de database als placeholders gebruikt.

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

Secties en componenten zijn te herordenen met ↑↓-knoppen en te verwijderen. Alles slaat automatisch op in `localStorage` met een debounce van 800ms.

---

### Iteratie 8 — Sidebar als losgekoppelde module

De sidebar is geëxtraheerd naar een eigen ES-module zodat hij herbruikbaar is en niet verstrengeld met de app-logica.

**Drie onderdelen:**

- `components/sidebar.html` — de HTML-template (merk, gebruikersrij, nav-placeholder).
- `js/sidebar.js` — ES-module met geëxporteerde functies:
  - `initSidebar(mountId, onLessonClick)` — fetcht de template, bouwt de navigatieboom in 4 lagen.
  - `getCourses()` — geeft de samengestelde cursusstructuur terug voor `app.js`.
  - `getSectionsByPage()` — geeft secties per pagina-ID terug voor lesinhoud.
  - `syncSidebarActive(courseId, lessonId)` — markeert de actieve les.
  - `toggleGroup(id)` — vouwt een cursusgroep open of dicht.
  - `updateSidebarUser(student)` — vult naam, niveau en XP-balk in.
- `index.html` — de `<aside>` is vervangen door `<div id="sidebar-mount"></div>`.

---

### Iteratie 9 — Navigatiestijl van de sectiepaginering

De vorige/volgende-knoppen en dot-indicators zijn volledig herontworpen zodat ze passen bij de dark en light thema's en intuïtiever zijn in gebruik.

**Layout:**
- Bovenaan: alleen de dunne voortgangsbalk (breedte = percentage voltooide slides).
- Onderaan, gecentreerd: één navigatiebalk met `←` · dots · paginatelling · `→`.

**Dots:** met een pill-animatie: de actieve dot rekt uit naar 22px breedte. Elke dot heeft de sectienaam als tooltip en is klikbaar om direct naar die sectie te springen. Op de laatste slide vervangt een groene vinkjesknop de volgende-pijl.

---

### Iteratie 10 — Live database-koppeling

`courses.js` (hardgecodeerde data) is volledig vervangen door een live MySQL-koppeling via PHP-API-endpoints.

**Wat veranderde:**
- `config/db.settings.php` en `config/db.connection.php` — gescheiden settings en verbinding (PDO, utf8mb4 voor emoji-ondersteuning).
- `api/subjects.php`, `api/courses.php`, `api/pages.php`, `api/sections.php` — vier endpoints die JSON teruggeven.
- `sidebar.js` laadt data in vier progressieve lagen: eerst de vakgroep-koppen, dan de cursusgroepen, dan de pagina's, dan de secties. Elke laag rendert direct zodra hij binnenkomt.
- Pagina's worden alleen getoond als ze ten minste één sectie hebben (`EXISTS`-filter in `api/pages.php`).

---

### Iteratie 11 — Sectie-uitklap in de sidebar

Pagina's met secties krijgen een uitklapfunctie in de sidebar.

**Gedrag:**
- Bij hover over een paginaregel verschijnt een blauw pijltje (▶) vóór de type-badge.
- Klikken op het pijltje klapt de secties uit (pijl roteert 90°). De secties blijven zichtbaar totdat je opnieuw klikt of de muis het gebied verlaat.
- Bij weggaan van de muis: secties klappen dicht, pijl roteert terug, en vervaagt daarna pas — zodat de animatie netjes voltooid wordt voor de pijl verdwijnt.
- Secties zijn gestyled als paginaregels maar met extra inspringing en een kleinere statuscirkel.

---

### Iteratie 12 — Lesweergave vanuit de sidebar

Klikken op een pagina in de sidebar opent de lesweergave in het midden en verbergt het dashboard.

**Gedrag:**
- Het dashboard verdwijnt; de lesweergave toont de paginatitel, broodkruimel, type-badge en het rechterpaneel.
- De secties uit de database worden als slides geladen: elke sectie is een eigen slide met de bestaande dot-navigatie en vorige/volgende-knoppen.
- Als een pagina al een HTML-lesbestand heeft (`lesson.file`), wordt dat geladen. Anders dienen de sectietitels uit de database als placeholder.

**Weergavewisseling:**
- Een knop bovenaan de lesweergave ("Switch to full view") toont alle secties tegelijk op één pagina.
- Klikken schakelt terug naar de slide-weergave ("Switch to section view").
- Bij het openen van een nieuwe pagina reset de weergave altijd naar sectieweergave.

**UI-opschoning:**
- Het "Pagina voltooid"-label onderaan de laatste slide is verwijderd. De groene vinkjesknop geeft al aan dat de pagina klaar is.
- De "Sectie X van Y"-teller in het rechterpaneel ("Jouw voortgang") is verwijderd — de dot-indicator in de navigatiebalk geeft deze informatie al visueel weer.

---

## Een les toevoegen

1. Voeg de pagina toe in de database (`Pages`-tabel) met de juiste `Course_Id`, `title`, `order`, `published = 1` en `PageType_Id`.
2. Voeg de secties toe in de `Sections`-tabel met de bijbehorende `Pages_Id`, `Title` en `Order`.
3. De sidebar en lesweergave laden de nieuwe pagina automatisch bij de volgende refresh.

Voor rijke lesinhoud: maak een HTML-bestand aan in `lessons/` met `<section data-title="...">` blokken en sla het pad op als toekomstig veld in de `Pages`-tabel.

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
| PHP + MySQL | Back-end + database | Live data, makkelijk te hosten op XAMPP, geen Node.js nodig |
| PDO utf8mb4 | Database-verbinding | Volledige Unicode inclusief emoji-iconen |
| ES modules | `import`/`export` in `sidebar.js` | Losse verantwoordelijkheden, herbruikbare componenten |
| 4-laagse sidebar | Progressieve render | Elke laag zichtbaar zodra die binnenkomt, geen laadscherm |
| EXISTS-filter | `api/pages.php` | Alleen pagina's met content tonen aan studenten |
| Één sectie per slide | Paginering | Duidelijke leerstap per keer, makkelijk te navigeren |
| CSS-variabelen | Themasysteem | Dark/light zonder JavaScript kleurlogica |
| `<section>` tags | Paginering | Expliciete splitpunten, fallback op `<h3>` |
| Cirkelpijlen + pill-dots | Sectienavigatie | Compacter dan tekst-knoppen, thema-neutraal, intuïtief |
| `localStorage` | Meldingen & admin | Geen server nodig in de huidige fase |
| Aparte `admin.html` | Docentenview | Geen code-menging met studentenview |
| Space Grotesk | UI-lettertype | Modern, leesbaar, karakter zonder cliché te zijn |
| JetBrains Mono | Code | Standaard in de ontwikkelwereld |
| GitHub Dark/Light | Kleurpaletten | Herkenbaar, professioneel, consistent met code-editors |

---

## Backlog

| Prioriteit | Feature |
|---|---|
| Hoog | Lesinhoud toevoegen per sectie (HTML-bestand of Content-veld in DB) |
| Hoog | Voortgang opslaan per student (localStorage of Supabase) |
| Hoog | Studentaccounts en inloggen |
| Hoog | Werkend quizsysteem met score en directe feedback |
| Middel | Meldingen naar een server sturen |
| Middel | Badges op basis van mijlpalen |
| Middel | Zoekfunctie over alle lessen |
| Laag | Video-embed als werkend component |
| Laag | Voortgang exporteren als PDF |
| Laag | Mobiele weergave verfijnen |
