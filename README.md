# ICT Leerlijn

**Gemaakt door:** Rick Dorr  
**Instelling:** ROC Midden Nederland — ICT-College  
**Rol:** Docent Software Development, MBO-3 en MBO-4

---

## Oorsprong van het project

Dit platform is ontstaan vanuit een concrete behoefte in de dagelijkse lespraktijk. Als docent Software Development bij ROC Midden Nederland geef ik les in Python, JavaScript (Processing/p5.js), Java, Unity 6, Unity 6 OpenXR/VR en twee MBO-rekencursussen (N3 en N4). Het lesmateriaal was versnipperd over Teams, losse PowerPoints, Word-documenten en externe sites als W3Schools — zonder structuur, zonder voortgangsregistratie, en zonder een centrale plek waar studenten altijd terecht konden.

De opdracht aan mezelf was eenvoudig: **bouw een leerplatform dat eruitziet en aanvoelt als Udemy of W3Schools, maar volledig is afgestemd op onze eigen vakken, onze eigen studenten en onze eigen manier van lesgeven.** Het platform moest modulair zijn zodat ik het makkelijk kon uitbreiden, en simpel genoeg dat ik het zonder externe diensten kon hosten en onderhouden.

Uit die behoefte is ICT Leerlijn gegroeid — iteratie voor iteratie, functie voor functie.

---

## Doelstelling

ICT Leerlijn biedt MBO-studenten Software Development een centrale leeromgeving met:

- Gestructureerd lesmateriaal per vak, georganiseerd in secties en pagina's
- Voortgang en gamification (XP, levels, streaks, badges)
- Een docenteninterface om lessen te maken zonder code te schrijven
- Studentaccounts met inloggen, rollen en cursuskoppelingen
- Een huiswerkbeheerder voor docenten om inzendingen te bekijken
- Rolgebaseerde toegang: superadmin, docent en student
- Een meldingssysteem waarmee studenten fouten in lesmateriaal kunnen rapporteren

---

## Designstijl

Het platform heeft een bewuste, consistente visuele identiteit die aansluit bij de wereld van softwareontwikkeling.

**Dark mode als standaard** — het donkere GitHub Dark kleurenpalet (`#0d1117` achtergrond, `#e6edf3` tekst) is de standaardmodus. Het voelt vertrouwd voor studenten die dagelijks in code-editors werken. Een schakelknop in de topbar wisselt naar een GitHub Light variant.

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
- Info-boxes in drie smaken: tip (blauw), waarschuwing (oranje), succes (groen)
- Opdrachtenboxen
- Quizvragen (meerkeuzevragen en open vragen)
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

## Rollen en rechten

Het platform kent drie rollen met verschillende toegangsniveaus:

| Rol | Toegang |
|---|---|
| **Superadmin** | Ziet alles, kan alles bewerken, accounts beheren, inzendingen nakijken. Geen beperkingen. |
| **Docent** | Ziet alleen eigen cursussen en eigen studenten. Kan lessen bewerken en inzendingen nakijken voor eigen cursussen. Kan voortgang bekijken van mentorstudenten, maar niet nakijken buiten eigen cursussen. |
| **Student** | Ziet alleen eigen cursussen. Kan lessen volgen, opdrachten inleveren en quizzen maken. |

**Docent-scoping werkt via twee relaties:**
- **Docent-cursus** (`Teacher_ParticipatesIn_Course`) — docent geeft les in deze cursus, kan nakijken en lessen bewerken
- **Docent-student / mentor** (`Teacher_guides_Student`) — docent begeleidt deze student, kan voortgang inzien maar niet nakijken

**Groepen** (`Groups`) — studenten worden ingedeeld in klassen (bijv. SD1A, SD1B). Docenten worden aan groepen gekoppeld via `Group_has_Teacher`.

---

## Snel starten

Het platform vereist XAMPP (Apache + MySQL). Een gewone statische server is niet voldoende omdat de API-endpoints PHP draaien.

1. Zet XAMPP aan (Apache + MySQL)
2. Zet het project in `htdocs/` of gebruik de `DocumentRoot` in Apache
3. Importeer in phpMyAdmin:
   - `database/CreateScriptRickLearningPlatform.sql` — maakt de database en tabellen
   - `database/seed.sql` — vult vakken, cursussen, pagina's, accounts en demodata
4. Open in de browser:
   - `http://localhost/` — studentenview (login/registratie)
   - `http://localhost/admin.html` — lesontwerper
   - `http://localhost/AccountManager.html` — accountbeheer
   - `http://localhost/HomeworkManager.html` — huiswerkbeheer

**Database-instellingen** staan in `config/db.settings.php`. Standaard: host `localhost`, geen wachtwoord.

**Demo-accounts** (wachtwoord voor allemaal: `wachtwoord123`):

| Gebruiker | Rol | Cursussen |
|---|---|---|
| Rick | superadmin | Alles (geen beperkingen) |
| Marloes | docent | Python, JavaScript. Mentor van JanWillem, Fatima, Daan |
| JanWillem | student | Python, Java |
| Fatima | student | Python, JavaScript, Unity 6 |
| Daan | student | Python, Unity 6, VR |
| Priya | student | JavaScript, Rekenen N4 |
| Thomas | student | Python, Java, Rekenen N3 |
| Yusuf | student | Python (inactief account) |

---

## Bestandsstructuur

```
ict-leerlijn/
|
|-- index.html                 <- Studentenview (login + lesomgeving)
|-- admin.html                 <- Lesontwerper (docentenview)
|-- AccountManager.html        <- Accountbeheer (superadmin/docent)
|-- HomeworkManager.html       <- Huiswerkbeheer (inzendingen nakijken)
|
|-- components/
|   +-- sidebar.html           <- Sidebar HTML-template
|
|-- config/
|   |-- db.settings.php        <- Host, databasenaam, gebruiker, wachtwoord
|   +-- db.connection.php      <- PDO-verbinding (gebruikt door alle API-bestanden)
|
|-- api/
|   |-- subjects.php           <- GET: alle vakgroepen
|   |-- courses.php            <- GET: alle cursussen met vakgroep
|   |-- pages.php              <- GET: gepubliceerde pagina's
|   |-- sections.php           <- GET: secties per pagina
|   |-- section_content.php    <- GET: componenten per sectie (tekst, code, quiz, etc.)
|   |-- login.php              <- POST: inloggen met gebruikersnaam/wachtwoord
|   |-- register.php           <- POST: nieuw account registreren
|   |-- me.php                 <- GET: huidige gebruiker + scope (cursussen, studenten)
|   |-- check_availability.php <- GET: controleer of gebruikersnaam/email beschikbaar is
|   |-- update_email.php       <- POST: e-mailadres wijzigen
|   |-- accounts.php           <- GET: alle accounts met inschrijvingen en voortgang
|   |-- account_toggle.php     <- POST: account activeren/deactiveren
|   |-- account_courses.php    <- POST/DELETE: student in-/uitschrijven voor cursus
|   |-- progress.php           <- GET: voltooide pagina's per student per cursus
|   |-- open_page.php          <- POST: pagina als geopend markeren
|   |-- submissions.php        <- GET: alle huiswerkinzendingen met cursus/pagina-info
|   |-- docent_courses.php     <- POST/DELETE: docent aan cursus koppelen/ontkoppelen
|   |-- docent_students.php    <- POST/DELETE: docent als mentor koppelen aan student
|   +-- upload_media.php       <- POST: media-bestanden uploaden
|
|-- css/
|   +-- main.css               <- Alle styling, dark + light mode, CSS-variabelen
|
|-- js/
|   |-- app.js                 <- App-logica: navigatie, paginering, meldingen
|   |-- sidebar.js             <- Sidebar ES-module (fetch, build, sync, export)
|   +-- highlighter.js         <- Syntaxkleuring voor codeblokken
|
|-- uploads/                   <- Geuploade bestanden (media, inzendingen)
|
+-- database/
    |-- CreateScriptRickLearningPlatform.sql  <- Schema (alle tabellen)
    |-- seed.sql                              <- Demodata (vakken, accounts, inzendingen)
    +-- drop_database.sql                     <- Reset
```

---

## Databaseschema

```
Subjects --> Courses --> Pages --> Sections --> Components
                |                                  |
                |                          +-------+-------+
                |                          |       |       |
                |                     TextBlocks CodeSnippets PQQuestion --> PQAnswer
                |                          |       |       |
                |                     InfoBoxes MultiMedia Assigments
                |
     Student_Has_Course              Accounts_have_assignments
                |                              |
            Accounts ------------- AC_Did_Question --> AC_Picked_Answer
                |
    +-----------+-----------+
    |           |           |
Teacher_     Teacher_    Student_
Participates guides     BelongsTo
In_Course   Student     Group
                           |
                        Groups --> Group_has_Teacher
```

**30 tabellen** georganiseerd rond drie kernconcepten:

| Concept | Tabellen |
|---|---|
| **Lesinhoud** | Subjects, Courses, PageTypes, Pages, Sections, ComponentType, Components, TextBlocks, CodeSnippets, InfoBoxes, MultiMedia, MultiMediaType, Languages, EmptySpace, EmptySpaceTypes |
| **Opdrachten & quizzen** | Assigments, Accounts_have_assignments, PQQuestion, PQAnswer, QuestionContext, AC_Did_Question, AC_Picked_Answer |
| **Gebruikers & rollen** | Accounts, Student_Has_Course, Accounts_opened_pages, Teacher_ParticipatesIn_Course, Teacher_guides_Student, Groups, Group_has_Teacher, Student_BelongsTo_Group |

---

## Pagina's

### Studentenview (`index.html`)

De hoofdpagina voor studenten. Bevat login/registratie, een sidebar met cursusnavigatie, en de lesweergave met sectiepaginering. Studenten zien alleen cursussen waarvoor ze zijn ingeschreven.

### Lesontwerper (`admin.html`)

Een twee-koloms editor voor docenten om lessen te ontwerpen zonder code te schrijven. Links een structuurpaneel met cursussen en pagina's, rechts de editor met secties en componenten. Docenten zien alleen hun eigen toegewezen cursussen.

### Accountbeheer (`AccountManager.html`)

Beheer van studentaccounts, cursusinschrijvingen en voortgang. Twee views: per student (tabel met filters, bulk-inschrijvingen) en per cursus (cursuskaarten met studentenlijst). Docenten zien alleen studenten in hun cursussen of mentorstudenten. Activate/deactivate is alleen voor superadmins.

### Huiswerkbeheer (`HomeworkManager.html`)

Overzicht van alle ingeleverde opdrachten. Toont statistieken (totaal, na te kijken, laatste 7 dagen, aantal studenten), filterbaar op cursus, status en type. Uitklapbare rijen tonen het volledige antwoord (tekst en/of bestand). Voor docenten: eigen cursussen zijn nakijkbaar, mentorstudenten tonen "Alleen inzien" badge. Beoordelen met score en feedback is nog in ontwikkeling.

---

## Architectuur

```
MySQL DB
  +-- api/*.php              PHP-queries, JSON output, rolgebaseerde data
        +-- js/sidebar.js    Fetcht 4 lagen, bouwt navigatieboom, exporteert data
              +-- js/app.js  Navigatie, lesweergave, paginering, syntaxkleuring
```

De sidebar laadt data in vier opeenvolgende lagen:

1. **Subjects** — vakgroepskoppen (Programmeren, Game & VR, Rekenen MBO)
2. **Courses** — cursusgroepen per vakgroep met icoon en kleur
3. **Pages** — lesitems per cursus (alleen gepubliceerd en heeft secties)
4. **Sections** — sub-items per pagina, inklapbaar via een pijl

Alle admin-pagina's volgen hetzelfde patroon:
1. Auth check via `api/me.php` — controleert rol en haalt scope op
2. Data fetch — haalt relevante data op
3. Client-side filtering — filtert op basis van docent-scope (cursussen + studenten)
4. Render — bouwt de tabel/interface op met filter-listeners

---

## Iteraties — wat is gebouwd en waarom

### Iteratie 1 — Interface & sidebar

Gebaseerd op de Udemy/W3Schools-ervaring: een collapsible sidebar met cursusgroepen, voortgangsbalken, statusindicatoren per les en type-badges (les / oefening / quiz / project). Rechts een hoofdgebied met het dashboard of de lesweergave.

**Keuze:** geen framework. Pure HTML/CSS/JS zodat het platform eenvoudig te begrijpen, te hosten en te onderhouden is.

---

### Iteratie 2 — Bestandsstructuur

Scheiding van data, logica en content. Lesinhoud als HTML-fragmenten in `lessons/`, app-logica in `app.js`, navigatie in `sidebar.js`.

---

### Iteratie 3 — Database schema

MySQL database met een vaste tabelhierarchie: subject, course, page, section. Cascade delete is ingesteld: een cursus verwijderen verwijdert ook alle bijbehorende pagina's en secties.

---

### Iteratie 4 — Sectiepaginering

Lessen worden gesplitst in slides: een sectie per slide. De student bladert door secties met vorige/volgende-pijlen. Een dot-indicator toont op welke sectie de student zit.

Bij een sectie: geen navigatiechrome. Bij meerdere: een dunne voortgangsbalk bovenaan en de navigatiebalk onderaan.

---

### Iteratie 5 — Dark / light thema

De schakelknop in de topbar wisselt tussen dark en light mode. Implementatie: `data-theme="light"` op het `<html>`-element schakelt een volledige set CSS-variabele-overrides in. Geen JavaScript voor kleurlogica — alles via CSS. De keuze wordt opgeslagen in `localStorage`.

---

### Iteratie 6 — Meldingensysteem

Bij hover op een sectie verschijnt een subtiel vlagje rechts naast de sectietitel. Klikken opent een compact modal met vier meldingstypen: fout in de inhoud, onduidelijke uitleg, probleem met de opdracht, of iets anders. Na versturen: toast-bevestiging, de vlag kleurt oranje als herinnering.

**Opslag:** `localStorage` onder `ict-reports`. Geen server nodig in de huidige fase.

---

### Iteratie 7 — Lesontwerper (admin.html)

Een aparte pagina voor docenten om lessen te ontwerpen zonder code te schrijven. Twee kolommen: links een structuurpaneel, rechts de editor met paginametadata en componenttypes (tekst, code, tip/waarschuwing, video, opdracht). Secties en componenten zijn te herordenen en te verwijderen.

---

### Iteratie 8 — Sidebar als losgekoppelde module

De sidebar is geextraheerd naar een eigen ES-module (`js/sidebar.js`) met geexporteerde functies: `initSidebar`, `getCourses`, `getSectionsByPage`, `syncSidebarActive`, `toggleGroup`, `updateSidebarUser`.

---

### Iteratie 9 — Navigatiestijl van de sectiepaginering

De vorige/volgende-knoppen en dot-indicators zijn herontworpen met een pill-animatie: de actieve dot rekt uit. Elke dot heeft de sectienaam als tooltip en is klikbaar.

---

### Iteratie 10 — Live database-koppeling

Hardgecodeerde data is vervangen door een live MySQL-koppeling via PHP-API-endpoints. Sidebar laadt data in vier progressieve lagen.

---

### Iteratie 11 — Sectie-uitklap in de sidebar

Pagina's met secties krijgen een uitklapfunctie in de sidebar. Bij hover verschijnt een pijltje, klikken klapt de secties uit.

---

### Iteratie 12 — Lesweergave vanuit de sidebar

Klikken op een pagina in de sidebar opent de lesweergave. Secties uit de database worden als slides geladen. Wissel tussen sectieweergave en volledige weergave.

---

### Iteratie 13 — Studentaccounts en authenticatie

Inlogsysteem met registratie, wachtwoord-hashing (bcrypt) en rolgebaseerde toegang. Drie rollen: student, docent en superadmin. Sessie via `sessionStorage`. Elke admin-pagina controleert de rol via `api/me.php` en redirect studenten naar de studentenview.

---

### Iteratie 14 — Accountbeheer (AccountManager.html)

Admin-pagina voor het beheren van studentaccounts. Twee views:
- **Per student** — tabel met zoeken, filteren op rol/status/cursus, bulk in-/uitschrijven, activeren/deactiveren
- **Per cursus** — cursuskaarten met studentenlijsten en direct in-/uitschrijven

Volgt hetzelfde UI-patroon als de lesontwerper: topbar met navigatie, statsrij, filerbalk en datatabellen.

---

### Iteratie 15 — Huiswerkbeheer (HomeworkManager.html)

Admin-pagina voor het bekijken van ingeleverde opdrachten. Toont alle inzendingen uit `Accounts_have_assignments` met cursus- en pagina-informatie. Stats, filters (zoeken, cursus, status, type), uitklapbare rijen met tekstantwoord en/of bestandsdownload. Beoordelen met score en feedback is voorbereid maar nog niet actief.

---

### Iteratie 16 — Docent-scoping en mentorrelaties

Twee nieuwe relaties toegevoegd:
- **Docent-cursus** — docent geeft les in een cursus, kan nakijken en lessen bewerken
- **Docent-student (mentor)** — docent begeleidt een student, kan voortgang inzien maar niet nakijken

Superadmins hebben geen beperkingen. Docenten zien in alle drie admin-pagina's alleen hun eigen scope:
- **Lesontwerper** — alleen toegewezen cursussen in de structuurboom
- **Huiswerkbeheer** — inzendingen van eigen cursussen (nakijkbaar) + mentorstudenten (alleen inzien)
- **Accountbeheer** — alleen studenten in eigen cursussen of mentorstudenten, geen activate/deactivate

Groepen (SD1A, SD1B, etc.) zijn aangemaakt in de database voor toekomstige koppeling met klassen.

---

## Een les toevoegen

1. Voeg de pagina toe in de database (`Pages`-tabel) met de juiste `Course_Id`, `title`, `order`, `published = 1` en `PageType_Id`.
2. Voeg de secties toe in de `Sections`-tabel met de bijbehorende `Pages_Id`, `Title` en `Order`.
3. De sidebar en lesweergave laden de nieuwe pagina automatisch bij de volgende refresh.

Of gebruik de **Lesontwerper** (`admin.html`) om pagina's en secties visueel aan te maken.

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
| Client-side scoping | Rolgebaseerde filtering | Server stuurt alle data, client filtert op scope van ingelogde gebruiker |
| Bcrypt wachtwoorden | `password_hash` in PHP | Veilige opslag, standaard in de branche |
| sessionStorage | Sessiedata | Simpel, verloopt bij sluiten van de tab, geen cookies nodig |
| CSS-variabelen | Themasysteem | Dark/light zonder JavaScript kleurlogica |
| Inline `<style>` per pagina | Admin-pagina styling | Elke admin-pagina heeft eigen overrides bovenop `main.css` |
| Space Grotesk | UI-lettertype | Modern, leesbaar, karakter zonder cliche te zijn |
| JetBrains Mono | Code | Standaard in de ontwikkelwereld |
| GitHub Dark/Light | Kleurpaletten | Herkenbaar, professioneel, consistent met code-editors |

---

## Backlog

| Prioriteit | Feature |
|---|---|
| Hoog | Beoordelen met score en feedback in Huiswerkbeheer (DB-migratie: Grade, Feedback, GradedOn kolommen) |
| Hoog | Quizvragen toevoegen aan Huiswerkbeheer (UNION van AC_Did_Question met submissions) |
| Hoog | Student-side inleverflow (opdracht inleveren vanuit de lesweergave) |
| Hoog | Docentbeheer-UI in AccountManager (superadmin wijst cursussen en mentorstudenten toe aan docent) |
| Middel | Groepenbeheer-UI (klassen aanmaken, studenten toewijzen) |
| Middel | Server-side scoping (API-endpoints filteren op rol i.p.v. alleen client-side) |
| Middel | Meldingen naar een server sturen (vervang localStorage) |
| Middel | Zoekfunctie over alle lessen |
| Laag | OpenAnswer en ReviewFeedback kolommen verbreden (VARCHAR(45) naar LONGTEXT) |
| Laag | Voortgang exporteren als PDF |
| Laag | Mobiele weergave verfijnen |
