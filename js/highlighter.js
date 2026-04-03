/**
 * ICT Leerlijn — Syntax Highlighter
 * Produces spans matching the platform's existing CSS classes:
 *   .kw  keyword        .fn  function name
 *   .st  string         .nm  number
 *   .cm  comment        .cl  class name (PascalCase)
 */

const KEYWORDS = {
  python: new Set([
    'False','None','True','and','as','assert','async','await',
    'break','class','continue','def','del','elif','else','except',
    'finally','for','from','global','if','import','in','is',
    'lambda','nonlocal','not','or','pass','raise','return',
    'try','while','with','yield',
  ]),
  javascript: new Set([
    'async','await','break','case','catch','class','const',
    'continue','debugger','default','delete','do','else','export',
    'extends','false','finally','for','from','function','if',
    'import','in','instanceof','let','new','null','of','return',
    'static','super','switch','this','throw','true','try',
    'typeof','undefined','var','void','while','with','yield',
  ]),
  java: new Set([
    'abstract','assert','boolean','break','byte','case','catch',
    'char','class','const','continue','default','do','double',
    'else','enum','extends','false','final','finally','float',
    'for','goto','if','implements','import','instanceof','int',
    'interface','long','native','new','null','package','private',
    'protected','public','return','short','static','strictfp',
    'super','switch','synchronized','this','throw','throws',
    'transient','true','try','void','volatile','while',
  ]),
  csharp: new Set([
    'abstract','as','base','bool','break','byte','case','catch',
    'char','checked','class','const','continue','decimal','default',
    'delegate','do','double','else','enum','event','explicit',
    'extern','false','finally','fixed','float','for','foreach',
    'goto','if','implicit','in','int','interface','internal','is',
    'lock','long','namespace','new','null','object','operator',
    'out','override','params','private','protected','public',
    'readonly','ref','return','sbyte','sealed','short','sizeof',
    'stackalloc','static','string','struct','switch','this',
    'throw','true','try','typeof','uint','ulong','unchecked',
    'unsafe','ushort','using','var','virtual','void','volatile',
    'while',
  ]),
};

function langKey(language) {
  if (!language) return null;
  const l = language.toLowerCase();
  if (l === 'c#' || l === 'csharp') return 'csharp';
  if (l === 'javascript' || l === 'js') return 'javascript';
  if (l === 'java') return 'java';
  if (l === 'python') return 'python';
  return null;
}

function esc(str) {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

/**
 * Highlight a single line of code.
 * Processes tokens left-to-right so strings/comments
 * are consumed before keyword matching.
 */
function highlightLine(line, kw, singleLineComment) {
  let out = '';
  let i   = 0;
  const len = line.length;

  while (i < len) {
    const ch = line[i];

    // ── Single-line comment ──────────────────────
    if (singleLineComment && line.startsWith(singleLineComment, i)) {
      out += `<span class="cm">${esc(line.slice(i))}</span>`;
      break;
    }

    // ── String (double-quoted) ───────────────────
    if (ch === '"') {
      let j = i + 1;
      while (j < len) {
        if (line[j] === '\\') { j += 2; continue; }
        if (line[j] === '"')  { j++;    break; }
        j++;
      }
      out += `<span class="st">${esc(line.slice(i, j))}</span>`;
      i = j;
      continue;
    }

    // ── String (single-quoted) ───────────────────
    if (ch === "'") {
      let j = i + 1;
      while (j < len) {
        if (line[j] === '\\') { j += 2; continue; }
        if (line[j] === "'")  { j++;    break; }
        j++;
      }
      out += `<span class="st">${esc(line.slice(i, j))}</span>`;
      i = j;
      continue;
    }

    // ── Number ───────────────────────────────────
    if (/[0-9]/.test(ch) && (i === 0 || /\W/.test(line[i - 1]))) {
      let j = i;
      while (j < len && /[0-9._xXa-fA-F]/.test(line[j])) j++;
      out += `<span class="nm">${esc(line.slice(i, j))}</span>`;
      i = j;
      continue;
    }

    // ── Identifier: keyword / class / function ───
    if (/[a-zA-Z_$]/.test(ch)) {
      let j = i;
      while (j < len && /[a-zA-Z0-9_$]/.test(line[j])) j++;
      const word   = line.slice(i, j);
      const isFunc = line[j] === '(';
      const isCls  = /^[A-Z]/.test(word) && word.length > 1;

      if (kw && kw.has(word)) {
        out += `<span class="kw">${esc(word)}</span>`;
      } else if (isFunc && !( kw && kw.has(word))) {
        out += `<span class="fn">${esc(word)}</span>`;
      } else if (isCls) {
        out += `<span class="cl">${esc(word)}</span>`;
      } else {
        out += esc(word);
      }
      i = j;
      continue;
    }

    out += esc(ch);
    i++;
  }

  return out;
}

/**
 * Highlight a block of code.
 * @param {string} code     Raw source code
 * @param {string} language Language name (e.g. "Python", "JavaScript")
 * @returns {string}        HTML string with syntax spans
 */
export function highlight(code, language) {
  const key = langKey(language);
  const kw  = key ? KEYWORDS[key] : null;
  const commentChar = (key === 'python') ? '#' : '//';

  return code
    .split('\n')
    .map(line => highlightLine(line, kw, commentChar))
    .join('\n');
}
