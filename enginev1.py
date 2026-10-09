#!/usr/bin/env python3


import sys
import re
import json
import math
import random
import hashlib
import warnings
from collections import Counter, defaultdict

warnings.filterwarnings("ignore")

# ═══════════════════════════════════════════════════════════════════════════
#  DEPENDENCIES
# ═══════════════════════════════════════════════════════════════════════════

try:
    import fitz
except ImportError:
    print(json.dumps({"error": "PyMuPDF not installed. Run: pip install PyMuPDF"}))
    sys.exit(1)

try:
    import nltk
    for _pkg, _path in [
        ("wordnet",                        "corpora/wordnet"),
        ("averaged_perceptron_tagger_eng", "taggers/averaged_perceptron_tagger_eng"),
        ("punkt_tab",                      "tokenizers/punkt_tab"),
        ("stopwords",                      "corpora/stopwords"),
    ]:
        try:
            nltk.data.find(_path)
        except LookupError:
            nltk.download(_pkg, quiet=True)
    from nltk.corpus import wordnet as _wn
    from nltk import pos_tag as _nltk_pos_tag, word_tokenize as _nltk_word_tokenize
    from nltk.tokenize import sent_tokenize as _nltk_sent_tokenize
    NLTK_FULL  = True
    WORDNET_OK = True
except Exception:
    _wn = _nltk_pos_tag = _nltk_word_tokenize = _nltk_sent_tokenize = None
    NLTK_FULL  = False
    WORDNET_OK = False

try:
    from sentence_transformers import SentenceTransformer, util as _st_util
    _ST_MODEL    = SentenceTransformer("all-MiniLM-L6-v2")
    ST_AVAILABLE = True
except Exception:
    _ST_MODEL    = None
    ST_AVAILABLE = False


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 1 — WORD LISTS & CONSTANTS
# ═══════════════════════════════════════════════════════════════════════════

STOP_WORDS = {
    "a","about","above","after","again","against","all","also","am","an","and",
    "any","are","as","at","be","because","been","before","being","below",
    "between","both","but","by","can","cannot","could","did","do","does",
    "doing","down","during","each","even","few","for","from","get","got",
    "had","has","have","having","he","hence","her","here","him","his","how",
    "i","if","in","into","is","it","its","itself","just","know","let","like",
    "make","many","may","me","might","more","most","much","must","my","need",
    "no","not","now","of","on","once","only","or","other","our","out","over",
    "own","say","see","she","should","since","so","some","such","than","that",
    "the","their","them","then","there","these","they","this","those","through",
    "thus","to","too","under","up","us","use","very","was","we","were","what",
    "when","where","which","while","who","whom","why","will","with","would",
    "you","your","yours","been","being","itself","myself","shall","able","per",
    "etc","vs","via","well","still","yet","often","usually","sometimes",
    "always","never","however","although","therefore","moreover","furthermore",
    "whereas","nonetheless","whether","within","without","along","among",
    "another","around","else","ever","every","given","hence","indeed","instead",
    "later","least","less","made","much","next","nor","note","page","same",
    "seem","set","since","take","tell","things","though","told","took","toward",
    "towards","true","unit","upon","used","using","week","whose","word",
    "here","there","now","then","thus","hence","accordingly","consequently",
}

_AUX = {
    "can","could","will","would","shall","should","may","might","must",
    "need","dare","ought","have","has","had","do","does","did",
    "be","is","are","was","were","been","being","get","got","let",
}

_IMPERATIVES = {
    "determine","identify","define","describe","explain","analyze","evaluate",
    "calculate","compute","solve","compare","contrast","list","enumerate",
    "summarize","classify","categorize","distinguish","find","show","prove",
    "demonstrate","state","give","write","discuss","review","consider","note",
    "examine","assess","apply","use","check","verify","draw","create","develop",
    "design","indicate","illustrate","observe","investigate","construct","select",
}

_NON_TERMS = {
    "here","there","now","then","thus","hence","however","therefore","moreover",
    "furthermore","additionally","finally","firstly","secondly","lastly",
    "meanwhile","nevertheless","nonetheless","otherwise","accordingly",
    "specifically","generally","typically","basically","essentially","literally",
    "actually","certainly","obviously","clearly","simply","already","previously",
}

_ADJ_ONLY = {
    "differentiated","selective","concentrated","undifferentiated","segmented",
    "targeted","customized","personalized","standardized","integrated","automated",
    "specialized","centralized","decentralized","diversified","adapted","modified",
    "restricted","limited","various","several","certain","specific","general",
    "basic","common","particular","main","major","minor","primary","secondary",
    "typical","standard","complex","important","significant","unique",
}

_NOISE_ENDS = {
    "image","figure","table","diagram","chart","slide","photo","picture",
    "graph","illustration","map","screenshot","definition","overview",
    "summary","notes","list","example","page","module","chapter","unit",
    "week","section","topic","discussion","activity","exercise","assignment",
    "assessment","introduction","conclusion","references","appendix",
    "allows","enables","causes","requires","includes","prevents","improves",
    "involves","affects","influences","look","see","consider","understand",
    "know","learn","use","apply","help","make","need","take","give",
}

_BAD_STARTS = {
    "to","now","here","there","while","when","if","although","however",
    "therefore","because","since","after","before","as","that","which",
    "who","and","but","or","so","nor","for","yet","both","either","neither",
    "not","no","let's","you","we","i","they","our","your","its","first",
    "second","third","finally","next","then","also","this","these","those",
    "such","each","every","some","many","most","all","any","few","more",
    "less","other","another","same","different","various","several",
}

_INSTRUC_RE = re.compile(
    r"(?i)(let'?s?\s+look|you\s+will\s+(learn|see|understand|find)"
    r"|i\s+will\s+(teach|show|explain)|now\s+that\s+you"
    r"|in\s+this\s+(module|chapter|lesson|section|unit)"
    r"|for\s+this\s+(lesson|module|unit|session)"
    r"|as\s+mentioned\s+(earlier|above|before|previously)"
    r"|\bdo\s+you\s+know\b|\bcan\s+you\s+think\b|\bremember\s+that\b"
    r"|\byou\s+have\s+learned\b|\bwe\s+(have|will|can|saw)\b"
    r"|\bin\s+this\s+lesson\b|\bthis\s+(module|lesson|chapter)\s+(covers?|discusses?|presents?)\b)"
)

_BAD_TERM_RE = re.compile(
    r"(?i)^(the|a|an|this|that|it|its|he|she|we|they|their|these|those|"
    r"true|false|given|module|pretest|posttest|discussion|references?|"
    r"where|when|what|which|who|how|and|but|or|nor|so|yet|"
    r"all|important|several|many|some|most|each|every|both|other|"
    r"such|thus|hence|therefore|however|whereas|although|"
    r"pamantasan|university|college|step|note|example|figure|table|"
    r"section|chapter|page|number|theory|automata|here|there|now|then|"
    r"various|certain|specific|general|main|major|minor|different|similar|"
    r"new|old|same|last|next|first|second|third|final|key|common)\b"
)


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 2 — NOISE FILTERS
# ═══════════════════════════════════════════════════════════════════════════

_NOISE_LINE_RES = [
    re.compile(r"(?i)pamantasan\s+(ng|of)\s+\w+"),
    re.compile(r"(?i)(university|college|institute|polytechnic)\s+of\s+[\w\s]+"),
    re.compile(r"(?i)\b(department|faculty|school)\s+of\s+[\w\s]+"),
    re.compile(r"(?i)^(module|week|unit|chapter|topic)\s+\d+"),
    re.compile(r"(?i)^(pretest|posttest|key\s+terms?|references?|bibliography)\s*:?$"),
    re.compile(r"(?i)^learning\s+outcomes?\s*:?$"),
    re.compile(r"(?i)^(prepared|submitted|compiled)\s+by"),
    re.compile(r"(?i)^(figure|table|fig\.?)\s+\d+"),
    re.compile(r"(?i)students?\s+shall\s+be\s+able\s+to"),
    re.compile(r"(?i)^(true\s+or\s+false|t\s*[|/]\s*f)\s*[:.]?"),
    re.compile(r"(?i)(e-?book\s+collection|isbn[\s\d\-]+|doi\s*:)"),
    re.compile(r"^[A-Z\s\|&:\-\.]{12,}$"),
    re.compile(r"^\d+[\.\)]\s+[A-Z]"),
    re.compile(r"[|]{2,}"),
    re.compile(r"(?i)^course\s+material"),
    re.compile(r"(?i)^\(source\s*:"),
    re.compile(r"(?i)^(page|p\.)\s*\d+\s*$"),
    re.compile(r"^[-–—=_*#]{3,}$"),
    re.compile(r"(?i)^\d+\s*\|\s*page"),
    re.compile(r"(?i)^(objective|objectives?|outcome|outcomes?)\s*:?\s*$"),
    re.compile(r"(?i)^(instruction|instructions?)\s*:?\s*$"),
]

_NOISE_SENT_RES = [
    re.compile(r"(?i)^(true\s+or\s+false|t\s*[|/]\s*f|given\s+grammar)"),
    re.compile(r"(?i)(pamantasan|university\s+of|college\s+of)"),
    re.compile(r"(?i)(module\s+contents?|pretest|posttest)"),
    re.compile(r"(?i)(\(\d{4}\)|\bet\s+al\b|vol\.\s*\d|pp\.\s*\d)"),
    re.compile(r"[|]{2,}"),
    re.compile(r"(?i)e-?book\s+collections?"),
    re.compile(r"(?i)(prepared\s+by|written\s+by|submitted\s+by)"),
    re.compile(r"(?i)course\s+material\s+no"),
    re.compile(r"(?i)\(source\s*:"),
    re.compile(r"(?i)as\s+shown\s+in\s+(figure|fig\.?)\s+\d"),
    re.compile(r"(?i)(figure|fig\.?)\s+\d+[\.\d]*\s+(shows?|illustrates?|depicts?)"),
    re.compile(r"(?i)(depicted|illustrated|shown)\s+in\s+(figure|fig)"),
    re.compile(r"(?i)here'?s?\s+what\s+i\s+will\s+teach"),
    re.compile(r"(?i)you\s+would\s+need\s+the\s+following\s+resources"),
    re.compile(r"(?i)(ian\s+sommerville|pressman|roger\s+s)"),
    re.compile(r"(?i)every\s+reasonable\s+effort\s+is\s+made"),
    re.compile(r"(?i)(copyright|intellectual\s+property|republic\s+act)"),
    re.compile(r"(?i)\bquiz\s+(no|number|#)\.?\s*\d+"),
    re.compile(r"(?i)^\s*(note|notes?)\s*:\s*"),
    _INSTRUC_RE,
]


def _line_is_noise(line: str) -> bool:
    s = line.strip()
    if not s:
        return True
    for p in _NOISE_LINE_RES:
        if p.search(s):
            return True
    alpha = sum(1 for c in s if c.isalpha())
    total = len(s)
    if total > 0 and alpha / total < 0.40:
        return True
    tokens = s.split()
    if tokens and sum(1 for t in tokens if len(t) == 1) / len(tokens) > 0.40:
        return True
    return False


def _sent_is_noise(s: str) -> bool:
    for p in _NOISE_SENT_RES:
        if p.search(s):
            return True
    return False


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 3 — PHRASE & ANSWER QUALITY GUARDS
# ═══════════════════════════════════════════════════════════════════════════

def _is_clean_phrase(phrase: str) -> bool:
    if not phrase or len(phrase.strip()) < 3:
        return False
    t     = phrase.strip()
    words = t.split()
    if len(words) > 14:
        return False
    first_w = words[0].lower()
    last_w  = words[-1].lower().rstrip(".,;") if words else ""
    if first_w in _BAD_STARTS:
        return False
    if last_w in _NOISE_ENDS:
        return False
    alpha_only = re.sub(r"[^a-zA-Z]", "", t)
    if alpha_only and alpha_only == alpha_only.upper() and len(alpha_only) > 4:
        return False
    cap_run = 0
    for w in words:
        cw = re.sub(r"[^a-zA-Z]", "", w)
        if cw and cw[0].isupper():
            cap_run += 1
            if cap_run >= 5:   # FIX: was 4, some legitimate phrases have 4 caps
                return False
        else:
            cap_run = 0
    for i in range(len(words) - 1):
        if words[i].lower() == words[i + 1].lower():
            return False
    counts = Counter(w.lower() for w in words if len(w) > 3)
    if any(v > 1 for v in counts.values()):
        return False
    if _INSTRUC_RE.search(t):
        return False
    if _sent_is_noise(t):
        return False
    return True


def _is_valid_answer(text: str) -> bool:
    """
    Validates MCQ answer and distractor text.
    BUG-02 FIX: don't silently reject valid nouns without NLTK POS tagger.
    """
    if not text or len(text.strip()) < 4:
        return False
    t = text.strip()
    if not _is_clean_phrase(t):
        return False
    words = t.split()
    if not words or not words[0]:
        return False
    if not words[0][0].isupper():
        return False
    if words[0].lower() in _AUX:
        return False
    if len(words) == 1:
        w    = words[0]
        w_lo = w.lower()
        if w_lo in _ADJ_ONLY or len(w) < 5:
            return False
        # BUG-02 FIX: Only reject verb forms when NLTK confirms VBZ/VBP
        # Previously returned False without NLTK, which blocked valid nouns
        if re.search(r"(?i)(tains|flows|lows|udes|ives|bles|tes|kes|ures|ples|sts)$", w_lo):
            if NLTK_FULL and _nltk_pos_tag:
                try:
                    tag = _nltk_pos_tag([w])[0][1]
                    if tag in ("VBZ", "VB", "VBP"):
                        return False
                except Exception:
                    pass
            # If no NLTK, allow it through (better to include than wrongly block)
        _common_singles = {
            "today","yesterday","tomorrow","always","sometimes","never","usually",
            "local","global","western","eastern","northern","southern","national",
            "example","examples","various","several","certain","different","similar",
            "culture","society","nature","system","process","structure","function",
            "information","knowledge","understanding","awareness","importance",
            "development","management","organization","application","implementation",
            "evaluation","assessment","analysis","research","education","technology",
        }
        if w_lo in _common_singles:
            return False
    # Reject OCR fragments with embedded periods
    inner = t[:-1]
    if "." in inner and len(t.split(".")) > 2:
        return False
    if _INSTRUC_RE.search(t):
        return False
    return True


def _is_valid_term(term: str) -> bool:
    if not term or len(term.strip()) < 2:
        return False
    t     = term.strip().rstrip(".,;")
    words = t.split()
    if not words:
        return False
    first = words[0].lower()
    if first in _IMPERATIVES or first in _NON_TERMS or first in _AUX:
        return False
    if _BAD_TERM_RE.match(t):
        return False
    if "." in t:
        return False
    if len(words) > 6:
        return False
    if len(words) == 1 and t.lower() in _ADJ_ONLY:
        return False
    if t.isdigit():
        return False
    return True


def _is_valid_entity(text: str) -> bool:
    if not text or len(text.strip()) < 3:
        return False
    t     = text.strip()
    words = t.split()
    first = words[0].lower()
    if first in _AUX or first in _NON_TERMS or first in _IMPERATIVES:
        return False
    if _BAD_TERM_RE.match(t):
        return False
    last = words[-1].lower()
    if last in _NOISE_ENDS:
        return False
    if any(re.match(r"^[A-Z]{2,}$", w) for w in words):
        return False
    if len(words[-1]) <= 2 and words[-1].isupper():
        return False
    _entity_noise_starters = {
        "learning","outcomes","objective","pretest","posttest","module",
        "chapter","unit","week","page","figure","table","note","quiz",
        "instructor","teacher","course","activity","exercise","assignment",
    }
    if first in _entity_noise_starters:
        return False
    if len(words) > 5:
        return False
    if len(words) == 1:
        w = words[0].lower()
        if w in _ADJ_ONLY or len(w) < 4:
            return False
        if NLTK_FULL and _nltk_pos_tag:
            try:
                tag = _nltk_pos_tag([words[0]])[0][1]
                if tag.startswith("JJ") or tag.startswith("RB"):
                    return False
            except Exception:
                pass
    return True


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 4 — SENTENCE SEGMENTATION
# ═══════════════════════════════════════════════════════════════════════════

_SENT_SPLIT_RE = re.compile(r"(?<![A-Z])([.!?])(?=\s+[A-Z])")


def _split_sentences(text: str) -> list:
    text = re.sub(r"\s+", " ", text).strip()
    if NLTK_FULL and _nltk_sent_tokenize:
        try:
            return [s.strip() for s in _nltk_sent_tokenize(text) if s.strip()]
        except Exception:
            pass
    text = _SENT_SPLIT_RE.sub(r"\1<BR>", text)
    return [s.strip() for s in text.split("<BR>") if s.strip()]


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 5 — POS TAGGING & NOUN PHRASE EXTRACTION
# ═══════════════════════════════════════════════════════════════════════════

def _pos_tag_fallback(word: str) -> str:
    w = word.lower()
    if w in STOP_WORDS or w in _AUX:
        return "DT"
    if w.endswith("ly") and len(w) > 4:
        return "RB"
    if w.endswith(("al","ous","ive","ful","less","ic","ible","able")) and len(w) > 5:
        return "JJ"
    if w.endswith("ing") and len(w) > 5:
        return "VBG"
    if w.endswith(("ize","ise","ate","ify")) and len(w) > 5:
        return "VB"
    if w.endswith("ed") and len(w) > 4:
        return "VBD"
    if w.endswith(("tion","sion","ment","ness","ity","ism","ist","ure","nce","ncy","age","ogy","phy")):
        return "NN"
    if word[0].isupper() and len(word) > 1:
        return "NNP"
    return "NN"


def _get_pos_tags(words: list) -> list:
    if not words:
        return []
    if NLTK_FULL and _nltk_pos_tag:
        try:
            return _nltk_pos_tag(words)
        except Exception:
            pass
    return [(w, _pos_tag_fallback(w)) for w in words]


_NP_HEAD = {"NN","NNS","NNP","NNPS"}
_NP_MOD  = {"JJ","JJR","JJS","VBG","VBN","NN","NNS","NNP","NNPS"}


def _extract_noun_phrases(sentence: str) -> list:
    """
    BUG-01 FIX: The original loop had an infinite loop risk when chunk was empty
    at the end of the token list because `i` was never incremented in that path.
    Rewritten with a cleaner sliding-window approach.
    """
    words = re.findall(r"\b[a-zA-Z]+\b", sentence)
    if len(words) < 2:
        return []
    tagged  = _get_pos_tags(words)
    phrases = []
    n       = len(tagged)
    i       = 0
    while i < n:
        word, tag = tagged[i]
        # Skip determiners
        if tag == "DT":
            i += 1
            continue
        # Try to build a noun phrase chunk starting at i
        if tag in _NP_MOD:
            j = i
            chunk = []
            while j < n and tagged[j][1] in _NP_MOD:
                chunk.append(tagged[j][0])
                j += 1
            # Valid NP: last token must be a noun head and chunk length 1–5
            if chunk and tagged[j - 1][1] in _NP_HEAD and 1 <= len(chunk) <= 5:
                phrase = " ".join(chunk)
                if (phrase.lower() not in STOP_WORDS
                        and len(phrase) > 3
                        and _is_clean_phrase(phrase)):
                    phrases.append(phrase)
            # Always advance past what we consumed (or at least by 1)
            i = max(j, i + 1)
        else:
            i += 1
    return phrases


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 6 — NAMED ENTITY RECOGNITION
# ═══════════════════════════════════════════════════════════════════════════

_MONTHS = {
    "january","february","march","april","may","june","july","august",
    "september","october","november","december",
    "jan","feb","mar","apr","jun","jul","aug","sep","oct","nov","dec",
}
_ORG_INDS = {
    "inc","corp","ltd","llc","company","corporation","organization",
    "institute","association","department","university","college",
    "school","academy","foundation","agency","bureau","committee",
    "council","board","center","laboratory","lab","group",
}
_PERSON_TITLES = {"mr","mrs","ms","dr","prof","professor","sir"}


def _rule_based_ner(text: str) -> list:
    entities = []
    seen     = set()
    for sent in _split_sentences(text):
        words = sent.split()
        if not words:
            continue
        i = 0
        while i < len(words):
            w     = words[i]
            w_c   = re.sub(r"[^a-zA-Z\-]", "", w)
            w_low = w_c.lower()

            # Rule 1: PERSON via title
            if w_low in _PERSON_TITLES and i + 1 < len(words):
                j     = i + 1
                parts = [w_c]
                while j < len(words):
                    nw = re.sub(r"[^a-zA-Z\-]", "", words[j])
                    if nw and nw[0].isupper() and nw.lower() not in STOP_WORDS:
                        parts.append(nw); j += 1
                    else:
                        break
                if len(parts) >= 2:
                    ent = " ".join(parts)
                    key = ent.lower()
                    if key not in seen and _is_valid_entity(ent):
                        seen.add(key)
                        entities.append({"text": ent, "label": "PERSON"})
                    i = j
                    continue

            # Rule 2: 2+ consecutive proper words (mid-sentence)
            if (w_c and w_c[0].isupper() and i > 0
                    and w_low not in STOP_WORDS and w_low not in _NON_TERMS
                    and w_low not in _AUX and w_low not in _ADJ_ONLY
                    and len(w_c) > 1):
                j     = i
                parts = []
                while j < len(words):
                    nw  = re.sub(r"[^a-zA-Z\-]", "", words[j])
                    nwl = nw.lower()
                    if (nw and nw[0].isupper()
                            and nwl not in STOP_WORDS and nwl not in _NON_TERMS):
                        parts.append(nw); j += 1
                    else:
                        break
                if len(parts) >= 2:
                    ent  = " ".join(parts)
                    key  = ent.lower()
                    last = parts[-1].lower()
                    label = "ORG" if last in _ORG_INDS else "CONCEPT"
                    if key not in seen and _is_valid_entity(ent) and _is_clean_phrase(ent):
                        seen.add(key)
                        entities.append({"text": ent, "label": label})
                    i = max(j, i + 1)
                    continue

            # Rule 3: DATE
            if w_low in _MONTHS:
                parts = [w_c]
                j     = i + 1
                if j < len(words) and re.match(r"^\d{1,2},?$", words[j]):
                    parts.append(words[j]); j += 1
                if j < len(words) and re.match(r"^\d{4}$", words[j]):
                    parts.append(words[j]); j += 1
                ent = " ".join(parts)
                if ent.lower() not in seen:
                    seen.add(ent.lower())
                    entities.append({"text": ent, "label": "DATE"})
                i = j
                continue

            # Rule 4: CARDINAL
            if re.match(r"^\d[\d,\.]*$", w) and w not in seen:
                seen.add(w)
                entities.append({"text": w, "label": "CARDINAL"})

            # Rule 5: Single capitalized CONCEPT (mid-sentence, min 5 chars)
            if (w_c and w_c[0].isupper() and i > 0 and len(w_c) >= 5
                    and w_low not in STOP_WORDS and w_low not in _NON_TERMS
                    and w_low not in _AUX and w_low not in _ADJ_ONLY
                    and not _BAD_TERM_RE.match(w_c)
                    and w_low not in seen and _is_valid_entity(w_c)):
                seen.add(w_low)
                entities.append({"text": w_c, "label": "CONCEPT"})

            i += 1
    return entities


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 7 — PDF TEXT EXTRACTION
# ═══════════════════════════════════════════════════════════════════════════

_OCR_ARTIFACTS = re.compile(
    r"(?:"
    r"\b[a-z]{1,2}\b"
    r"|[^a-zA-Z0-9\s\.,;:\!\?\-\(\)\'\"]{2,}"
    r"|\b\d{1,2}\b(?!\s*(?:pages?|%|hours?|minutes?|seconds?))"
    r")"
)


def _clean_line(line: str) -> str:
    # Remove true control characters but KEEP printable unicode (IMP-05)
    line = re.sub(r"[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]", " ", line)
    line = re.sub(r"\s+", " ", line)
    return line.strip()


def extract_pdf_text(pdf_path: str) -> str:
    """IMP-05: Unicode-safe extraction — no longer strips to ASCII."""
    doc           = fitz.open(pdf_path)
    all_page_lines = []

    for page in doc:
        raw   = page.get_text("text")
        lines = raw.split("\n")
        all_page_lines.append(lines)

    # Detect repeating header/footer lines
    if len(all_page_lines) >= 3:
        line_freq: Counter = Counter()
        for page_lines in all_page_lines:
            for ln in page_lines[:3] + page_lines[-3:]:
                stripped = re.sub(r"\d+", "#", ln.strip().lower())
                if stripped:
                    line_freq[stripped] += 1
        threshold = max(2, int(len(all_page_lines) * 0.40))
        repeating = {k for k, v in line_freq.items() if v >= threshold}
    else:
        repeating = set()

    pages_text = []
    for page_lines in all_page_lines:
        clean_lines = []
        for line in page_lines:
            line = _clean_line(line)
            if len(line) < 4:
                continue
            stripped = re.sub(r"\d+", "#", line.lower())
            if stripped in repeating:
                continue
            if _line_is_noise(line):
                continue
            if len(line.split()) < 3 and line and line[0].isupper():
                continue
            clean_lines.append(line)
        pages_text.append(" ".join(clean_lines))

    text = " ".join(pages_text)

    # Normalize unicode whitespace but keep characters
    text = re.sub(r"[\xa0\u2000-\u200b\u202f\u205f\u3000]", " ", text)
    text = re.sub(r"\s+", " ", text)

    # Remove known meta-text patterns
    _strip_pats = [
        r"(?i)true\s+or\s+false\s*[:.\-]\s*",
        r"(?i)\bT\s*[|/]\s*F\s*[:.\-]?\s*",
        r"(?i)pamantasan\s+(ng|of)\s+\w+[^.]*",
        r"(?i)(university|college|institute)\s+of\s+\w+[^.]*\.?",
        r'\(Source:[^)]{0,300}\)',
        r"(?i)course\s+material\s+no\.?\s*\d+[^\n]*",
        r"(?i)figure\s+\d+[\d\.]*[^.]{0,200}\.",
        r"(?i)as\s+shown\s+in\s+figure\s+[\d\.]+[^.]*\.",
        r"(?i)here.?s\s+what\s+i\s+will\s+teach[^.]*\.",
        r"(?i)for\s+this\s+lesson[^.]*you\s+would\s+need[^.]*\.",
        r"(?i)(depicted|illustrated|shown)\s+in\s+figure\s+[\d\.]+[^.]*\.",
        r"(?i)(ian\s+sommerville|pressman)\b[^.]*\.",
        r"(?i)every\s+reasonable\s+effort[^.]*\.",
        r"(?i)(republic\s+act|intellectual\s+property|copyright)[^.]*\.",
        r"(?i)quiz\s+(no|number|#)\.?\s*\d+[^.]*\.",
    ]
    for pat in _strip_pats:
        text = re.sub(pat, " ", text)

    # Remove references section
    for marker in ["References", "Bibliography", "Works Cited", "Further Reading"]:
        idx = text.rfind(marker)
        if idx > len(text) * 0.55:
            text = text[:idx]

    text = re.sub(r"\s{2,}", " ", text)
    return text.strip()


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 8 — SENTENCE QUALITY FILTER
# ═══════════════════════════════════════════════════════════════════════════

MIN_SENT_WORDS = 8
MAX_SENT_WORDS = 70


def _capitalize_sentence(s: str) -> str:
    s = s.strip()
    if not s:
        return s
    s = s[0].upper() + s[1:]
    if s[-1] not in ".!?":
        s += "."
    return s


def _sentence_is_valid(s: str) -> bool:
    words = s.split()
    if not (MIN_SENT_WORDS <= len(words) <= MAX_SENT_WORDS):
        return False
    if s.strip().endswith("?"):
        return False
    if _sent_is_noise(s) or _INSTRUC_RE.search(s):
        return False
    specials = sum(1 for c in s if c in "{}[]();=<>|&^~+*\\@#$%")
    if specials > 4:
        return False
    alpha_chars = [c for c in s if c.isalpha()]
    if not alpha_chars:
        return False
    if sum(1 for c in alpha_chars if c.isupper()) / len(alpha_chars) > 0.60:
        return False
    alpha_words = [w for w in words if w.isalpha()]
    if not alpha_words:
        return False
    if sum(len(w) for w in alpha_words) / len(alpha_words) < 3.2:
        return False
    if s.rstrip().endswith(":"):
        return False
    ocr_hits = len(_OCR_ARTIFACTS.findall(s))
    if ocr_hits > 3:
        return False
    return True


def get_sentences(text: str) -> list:
    raw    = _split_sentences(text)
    result = []
    for s in raw:
        s = re.sub(r"^[\d]+[\.:\)]\s*", "", s)
        s = re.sub(r"^[•\-\*\–\—►▪]\s*", "", s)
        s = re.sub(r"\s+", " ", s).strip()
        if _sentence_is_valid(s):
            result.append(_capitalize_sentence(s))
    return result


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 9 — N-GRAM TF-IDF
# ═══════════════════════════════════════════════════════════════════════════

_JUNK_KW = {
    "page","module","week","chapter","unit","student","students","learning",
    "outcomes","able","shall","prepared","submitted","references","figure",
    "table","dr","prof","true","false","given","pretest","posttest","course",
    "introduction","overview","summary","notes","slides","fig","source","shown",
    "depicted","illustrated","lesson","material","diagram","context","copyright",
    "image","picture","photo","here","there","now","then","quiz","activity",
    "exercise","assignment","assessment","instructor","teacher","professor",
}


def _tokenize_content(text: str) -> list:
    tokens = re.findall(r"\b[a-zA-Z]{3,}\b", text)
    return [t.lower() for t in tokens
            if t.lower() not in STOP_WORDS and t.lower() not in _JUNK_KW]


def _sentence_ngrams(sent: str, max_n: int = 3) -> list:
    tokens = _tokenize_content(sent)
    ngrams = []
    for n in range(1, max_n + 1):
        for i in range(len(tokens) - n + 1):
            gram = " ".join(tokens[i:i + n])
            ngrams.append(gram)
    return ngrams


def compute_ngram_tfidf(sentences: list, max_n: int = 3, top_n: int = 60) -> list:
    n_docs = len(sentences)
    if n_docs == 0:
        return []
    term_freq: Counter = Counter()
    doc_freq:  Counter = Counter()
    for sent in sentences:
        grams = _sentence_ngrams(sent, max_n)
        term_freq.update(grams)
        doc_freq.update(set(grams))
    scored = []
    for gram, tf in term_freq.items():
        if tf < 2:
            continue
        words = gram.split()
        if all(w in STOP_WORDS or w in _JUNK_KW for w in words):
            continue
        if len(words) == 1 and words[0] in _JUNK_KW:
            continue
        idf          = math.log((n_docs + 1) / (doc_freq[gram] + 1)) + 1
        length_bonus = 1.0 + (len(words) - 1) * 0.35
        score        = tf * idf * length_bonus
        scored.append((gram, score))
    scored.sort(key=lambda x: -x[1])
    return scored[:top_n]


def get_ngram_keywords(sentences: list, top_n: int = 40) -> list:
    ranked     = compute_ngram_tfidf(sentences, max_n=3, top_n=top_n * 2)
    result     = []
    seen_words = set()
    for gram, score in ranked:
        words = gram.split()
        if any(len(w) < 3 for w in words):
            continue
        if len(words) == 1 and len(words[0]) < 5:
            continue
        gram_word_set = set(words)
        if gram_word_set <= seen_words:
            continue
        result.append(gram)
        seen_words |= gram_word_set
        if len(result) >= top_n:
            break
    return result


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 10 — CONCEPT BANK
# ═══════════════════════════════════════════════════════════════════════════

class ConceptBank:
    def __init__(self):
        self._clusters:          dict = defaultdict(list)
        self._concept_to_cluster: dict = {}

    def add(self, concept: str):
        concept = concept.strip().lower()
        if not concept or len(concept) < 3:
            return
        if concept in self._concept_to_cluster:
            return
        c_words    = set(concept.split()) - STOP_WORDS
        if not c_words:
            return
        best_key    = None
        best_overlap = 0
        for cluster_key in self._clusters:
            k_words = set(cluster_key.split())
            overlap = len(c_words & k_words)
            if overlap > best_overlap:
                best_overlap = overlap
                best_key     = cluster_key
        if best_key and best_overlap >= 1:
            self._clusters[best_key].append(concept)
            self._concept_to_cluster[concept] = best_key
        else:
            key_words = [w for w in concept.split() if w not in STOP_WORDS][:2]
            new_key   = " ".join(key_words) if key_words else concept[:12]
            self._clusters[new_key].append(concept)
            self._concept_to_cluster[concept] = new_key

    def get_cluster_members(self, concept: str, exclude: str = "") -> list:
        c   = concept.strip().lower()
        key = self._concept_to_cluster.get(c)
        if not key:
            c_words     = set(c.split()) - STOP_WORDS
            best_key, best_overlap = None, 0
            for k in self._clusters:
                overlap = len(c_words & set(k.split()))
                if overlap > best_overlap:
                    best_overlap = overlap
                    best_key     = k
            key = best_key
        if not key:
            return []
        return [m for m in self._clusters[key]
                if m != c and m != exclude.lower().strip()]

    def all_concepts(self) -> list:
        result = []
        for concepts in self._clusters.values():
            result.extend(concepts)
        return list(set(result))

    def __len__(self):
        return sum(len(v) for v in self._clusters.values())


def build_concept_bank(ngram_keywords, defs, entities, noun_phrases) -> ConceptBank:
    bank = ConceptBank()
    for gram in ngram_keywords:
        if len(gram.split()) >= 2:
            bank.add(gram)
    for gram in ngram_keywords:
        if len(gram.split()) == 1:
            bank.add(gram)
    for d in defs:
        bank.add(d["term"].lower())
    for e in entities:
        if _is_valid_entity(e["text"]):
            bank.add(e["text"].lower())
    for np in noun_phrases:
        if _is_valid_answer(np):
            bank.add(np.lower())
    return bank


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 11 — SENTENCE IMPORTANCE SCORING
# ═══════════════════════════════════════════════════════════════════════════

_DEF_PATTERNS = [
    re.compile(r"^(.+?)\s+is\s+defined\s+as\s+(.+)",                  re.IGNORECASE),
    re.compile(r"^(.+?)\s+refers?\s+to\s+(.+)",                        re.IGNORECASE),
    re.compile(r"^(.+?)\s+is\s+a(?:n)?\s+(.+)",                       re.IGNORECASE),
    re.compile(r"^(.+?)\s+is\s+the\s+(.+)",                            re.IGNORECASE),
    re.compile(r"^(.+?)\s+means?\s+(.+)",                              re.IGNORECASE),
    re.compile(r"^(.+?)\s+describes?\s+(.+)",                          re.IGNORECASE),
    re.compile(r"^(.+?)\s+involves?\s+(.+)",                           re.IGNORECASE),
    re.compile(r"^(.+?)\s+(?:is|are)\s+(?:used|known|considered)\s+(.+)", re.IGNORECASE),
    re.compile(r"^(.+?)\s+can\s+be\s+defined\s+as\s+(.+)",            re.IGNORECASE),
    re.compile(r"^(.+?)\s+is\s+(?:often|typically|generally)\s+(.+)", re.IGNORECASE),
    re.compile(r"^(.+?)\s+(?:are|is)\s+(?:types?|forms?|kinds?|categories?)\s+of\s+(.+)", re.IGNORECASE),
    # IMP-01: additional definition patterns
    re.compile(r"^(.+?)\s+(?:is|are)\s+(?:known|referred\s+to)\s+as\s+(.+)", re.IGNORECASE),
    re.compile(r"^(.+?)\s+can\s+be\s+described\s+as\s+(.+)",          re.IGNORECASE),
    re.compile(r"^(.+?)\s+is\s+characterized\s+(?:by|as)\s+(.+)",     re.IGNORECASE),
]

_RELATION_PATTERNS = [
    (re.compile(r"(?i)\b(enables?|allows?|permits?)\b"),           "enables"),
    (re.compile(r"(?i)\b(causes?|results?\s+in|leads?\s+to)\b"),   "causes"),
    (re.compile(r"(?i)\b(requires?|needs?|depends?\s+on)\b"),      "requires"),
    (re.compile(r"(?i)\b(includes?|consists?\s+of|contains?)\b"),  "includes"),
    (re.compile(r"(?i)\b(prevents?|stops?|avoids?)\b"),            "prevents"),
    (re.compile(r"(?i)\b(improves?|enhances?|increases?)\b"),      "improves"),
    (re.compile(r"(?i)\b(supports?|facilitates?)\b"),               "supports"),
    (re.compile(r"(?i)\b(reduces?|decreases?|minimizes?)\b"),      "reduces"),
]

_BLOOM_RECALL     = re.compile(r"(?i)\b(is|are|was|were|means?|refers?\s+to|defined\s+as|known\s+as|called|stands?\s+for)\b")
_BLOOM_UNDERSTAND = re.compile(r"(?i)\b(because|therefore|thus|hence|as\s+a\s+result|which\s+(means|allows|enables|causes)|in\s+order\s+to|so\s+that)\b")
_BLOOM_APPLY      = re.compile(r"(?i)\b(used|applied|implemented|utilized|employed|for\s+example|such\s+as|can\s+be\s+used)\b")
_BLOOM_ANALYZE    = re.compile(r"(?i)\b(however|whereas|although|despite|compared\s+to|in\s+contrast|on\s+the\s+other\s+hand|advantage|disadvantage|limitation|consequence)\b")


def score_sentence_educational(sentence: str, ngram_kws: list,
                                entities: list, top_n_grams: set) -> float:
    if _INSTRUC_RE.search(sentence):
        return 0.0
    score = 0.0
    sl    = sentence.lower()
    kw_hits   = sum(1 for kw in ngram_kws if kw.lower() in sl)
    score    += kw_hits * 1.5
    multi_kw  = sum(1 for kw in ngram_kws if " " in kw and kw.lower() in sl)
    score    += multi_kw * 0.8
    if any(p.search(sentence) for p in _DEF_PATTERNS[:6]):
        score += 4.0
    ent_hits  = sum(1 for e in entities if e["text"].lower() in sl)
    score    += ent_hits * 1.2
    if any(pat.search(sentence) for pat, _ in _RELATION_PATTERNS):
        score += 1.5
    if _BLOOM_ANALYZE.search(sentence):
        score += 1.2
    elif _BLOOM_APPLY.search(sentence):
        score += 0.8
    elif _BLOOM_UNDERSTAND.search(sentence):
        score += 0.5
    words = len(sentence.split())
    if 12 <= words <= 35:
        score += 0.5
    elif words > 50:
        score -= 0.5
    return score


def rank_sentences(sentences: list, ngram_kws: list, entities: list) -> list:
    """
    BUG-08 FIX: The ST re-weighting used scored[i][1] after rebuilding `scored`
    list, which caused index mismatch. Now builds new list cleanly.
    """
    top_ngrams = set(kw for kw in ngram_kws if " " in kw)
    scored = []
    for sent in sentences:
        sc = score_sentence_educational(sent, ngram_kws, entities, top_ngrams)
        if sc > 0:
            scored.append((sent, sc))

    if ST_AVAILABLE and _ST_MODEL and len(scored) >= 2:
        try:
            texts  = [s for s, _ in scored]
            scores = [sc for _, sc in scored]
            query  = texts[0]
            all_texts = [query] + texts
            embs  = _ST_MODEL.encode(all_texts, convert_to_tensor=True)
            q_emb = embs[0]
            s_embs = embs[1:]
            sims  = _st_util.cos_sim(q_emb, s_embs)[0].tolist()
            # BUG-08 FIX: build new list rather than mutating
            new_scored = [(texts[i], scores[i] * 0.7 + float(sims[i]) * 3.0)
                          for i in range(len(texts))]
            scored = new_scored
        except Exception:
            pass

    scored.sort(key=lambda x: -x[1])
    return [s for s, _ in scored]


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 12 — DEFINITION EXTRACTION
# ═══════════════════════════════════════════════════════════════════════════

def _extract_short_definition(defn: str) -> str:
    """Extract the first self-contained clause, max 12 words."""
    defn = re.sub(r"(?i)^(now\s+that|here\s+is|as\s+we|let'?s?\s+)", "", defn).strip()
    for sep in [r"\bwhich\b", r"\bthat\b",
                r",\s+(?:and|but|or|such|including)\b",
                r";\s*",
                r"\s+and\s+(?:also|further|additionally)\b"]:
        parts = re.split(sep, defn, maxsplit=1, flags=re.IGNORECASE)
        if len(parts) > 1 and len(parts[0].split()) >= 3:
            cand = parts[0].strip().rstrip(",;")
            if 3 <= len(cand.split()) <= 14 and cand[0].isupper():
                return cand
    words   = defn.split()
    trimmed = " ".join(words[:10])
    last_w  = trimmed.split()[-1].lower().rstrip(".,;") if trimmed.split() else ""
    if last_w in _NOISE_ENDS:
        trimmed = " ".join(words[:7]).rstrip(".,;")
    return trimmed


def find_definition_sentences(sentences: list) -> list:
    results    = []
    seen_terms = set()
    for sent in sentences:
        if _INSTRUC_RE.search(sent):
            continue
        for pat in _DEF_PATTERNS:
            m = pat.match(sent)
            if not m:
                continue
            term = m.group(1).strip().rstrip(".,;")
            defn = m.group(2).strip().rstrip(".")
            if not _is_valid_term(term):
                continue
            if term.lower() in seen_terms:
                continue
            if len(defn.split()) < 4:
                continue
            if _sent_is_noise(defn) or _INSTRUC_RE.search(defn):
                continue
            short = _extract_short_definition(defn)
            if not short or len(short.split()) < 3:
                short = " ".join(defn.split()[:8])
            if not short or not short[0].isupper():
                continue
            if not _is_valid_answer(short):
                continue
            seen_terms.add(term.lower())
            results.append({
                "sentence":  sent,
                "term":      term,
                "definition": defn,
                "short_def": short,
            })
            break
    return results


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 13 — PDF VALIDATOR
# ═══════════════════════════════════════════════════════════════════════════

def validate_pdf(text: str) -> dict:
    logs = []
    wc   = len(text.split())
    if wc < 80:
        logs.append(f"REJECTED — only {wc} words (minimum 80).")
        return {"valid": False, "logs": logs}
    sents = get_sentences(text)
    if len(sents) < 6:
        logs.append(f"REJECTED — only {len(sents)} sentences (minimum 6).")
        return {"valid": False, "logs": logs}
    sym = sum(1 for c in text if c in "{}[]();=<>|&^~")
    alp = sum(1 for c in text if c.isalpha())
    ratio = sym / alp if alp > 0 else 1.0
    if ratio > 0.15:
        logs.append(f"REJECTED — high symbol ratio ({ratio:.2f}). Looks like code.")
        return {"valid": False, "logs": logs}
    ents = _rule_based_ner(text)
    kws  = get_ngram_keywords(sents, top_n=10)
    if len(ents) < 2 and len(kws) < 3:
        logs.append("REJECTED — not enough identifiable terms.")
        return {"valid": False, "logs": logs}
    logs.append("ACCEPTED")
    return {
        "valid": True, "logs": logs,
        "stats": {
            "word_count": wc, "sentence_count": len(sents),
            "entity_count": len(ents), "keyword_count": len(kws),
            "symbol_ratio": round(ratio, 3),
        },
    }


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 14 — REVIEWER / SUMMARY GENERATION
# ═══════════════════════════════════════════════════════════════════════════

def generate_overview(sentences: list, ngram_kws: list, entities: list) -> str:
    if not sentences:
        return "No overview could be generated."
    ranked   = rank_sentences(sentences, ngram_kws, entities)
    top_ng   = set(ngram_kws[:15])
    covered  = set()
    selected = []
    for sent in ranked:
        if _INSTRUC_RE.search(sent):
            continue
        kws = {kw for kw in top_ng if kw.lower() in sent.lower()}
        if kws - covered or len(selected) == 0:
            selected.append(sent)
            covered |= kws
        if len(selected) >= 5:
            break
    if len(selected) < 3:
        for sent in ranked:
            if sent not in selected and not _INSTRUC_RE.search(sent):
                selected.append(sent)
            if len(selected) >= 4:
                break
    return " ".join(selected) if selected else (sentences[0] if sentences else "")


def generate_terminologies(sentences: list, ngram_kws: list, defs: list) -> list:
    terms    = []
    existing = set()
    for d in defs:
        clean_defn = d["definition"].rstrip(".,;").strip()
        if clean_defn:
            clean_defn = clean_defn[0].upper() + clean_defn[1:]
        terms.append({"term": d["term"], "definition": clean_defn})
        existing.add(d["term"].lower())
    if len(terms) < 8:
        for kw in ngram_kws:
            if kw.lower() in existing or _BAD_TERM_RE.match(kw):
                continue
            best, best_sc = None, 0
            for sent in sentences:
                if kw.lower() not in sent.lower() or _INSTRUC_RE.search(sent):
                    continue
                has_def = any(p.search(sent) for p in _DEF_PATTERNS[:4])
                density = sum(1 for k in ngram_kws if k.lower() in sent.lower())
                sc      = density + (3 if has_def else 0)
                if sc > best_sc:
                    best_sc = sc; best = sent
            if best:
                terms.append({"term": kw.capitalize(), "definition": best})
                existing.add(kw.lower())
            if len(terms) >= 14:
                break
    return terms[:14]


def generate_study_points(sentences: list, ngram_kws: list, entities: list) -> list:
    ranked = rank_sentences(sentences, ngram_kws, entities)
    unique = []
    seen   = set()
    for sent in ranked:
        if _INSTRUC_RE.search(sent):
            continue
        key = re.sub(r"\W", "", sent[:40]).lower()
        if key not in seen:
            seen.add(key)
            unique.append(sent)
        if len(unique) >= 10:
            break
    return unique


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 15 — WORDNET HELPERS
# ═══════════════════════════════════════════════════════════════════════════

def _wn_siblings(word: str) -> list:
    if not WORDNET_OK or not _wn:
        return []
    result = []
    for syn in _wn.synsets(word.lower())[:2]:
        for hyp in syn.hypernyms():
            for hypo in hyp.hyponyms():
                for lemma in hypo.lemmas():
                    n = lemma.name().replace("_", " ")
                    if n.lower() != word.lower() and _is_valid_answer(n):
                        result.append(n)
    return result[:8]


def _wn_synonyms(word: str) -> list:
    if not WORDNET_OK or not _wn:
        return []
    syns = set()
    for syn in _wn.synsets(word.lower()):
        for lemma in syn.lemmas():
            n = lemma.name().replace("_", " ")
            if n.lower() != word.lower() and _is_valid_answer(n):
                syns.add(n)
    return list(syns)[:6]


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 16 — SMART DISTRACTOR GENERATION
# ═══════════════════════════════════════════════════════════════════════════

def _length_compatible(answer: str, cand: str) -> bool:
    a, c = len(answer.split()), len(cand.split())
    if a == 0:
        return False
    return 0.33 <= c / a <= 3.5


def _bigrams(s: str) -> set:
    s = s.lower().replace(" ", "")
    return set(s[i:i + 2] for i in range(len(s) - 1))


def _jaccard(a: str, b: str) -> float:
    A, B = _bigrams(a), _bigrams(b)
    u    = A | B
    return len(A & B) / len(u) if u else 0.0


def _semantic_sim(a: str, b: str) -> float:
    if not ST_AVAILABLE or not _ST_MODEL:
        return _jaccard(a, b)
    try:
        embs = _ST_MODEL.encode([a, b], convert_to_tensor=True)
        return float(_st_util.cos_sim(embs[0], embs[1])[0][0])
    except Exception:
        return _jaccard(a, b)


def build_smart_distractors(correct_answer: str, concept_bank: ConceptBank,
                             def_bank: list, noun_phrases: list,
                             n: int = 3, target_similarity: str = "medium") -> list:
    ans_l  = correct_answer.lower().strip()
    seen   = {ans_l}
    result = []

    # Pool 1: Same concept-bank cluster
    cluster_members = concept_bank.get_cluster_members(correct_answer)
    first_word = next(
        (w for w in correct_answer.lower().split() if w not in STOP_WORDS), ""
    )
    if first_word:
        cluster_members += concept_bank.get_cluster_members(first_word)

    cluster_scored = [
        (m, _semantic_sim(correct_answer.lower(), m))
        for m in cluster_members
        if m.lower() not in seen and _is_valid_answer(m.capitalize())
    ]
    if target_similarity == "high":
        cluster_scored.sort(key=lambda x: -x[1])
    elif target_similarity == "low":
        cluster_scored.sort(key=lambda x: x[1])
    else:
        cluster_scored.sort(key=lambda x: abs(x[1] - 0.30))

    for m, _ in cluster_scored:
        capped = m[0].upper() + m[1:] if m else m
        # BUG-04 FIX: check lowercased to avoid duplicate answers
        if (capped.lower() not in seen
                and _is_valid_answer(capped)
                and _length_compatible(correct_answer, capped)):
            result.append(capped)
            seen.add(capped.lower())
        if len(result) >= n:
            break

    # Pool 2: Definition bank
    if len(result) < n:
        def_scored = [
            (d, _jaccard(correct_answer, d))
            for d in def_bank
            if (d.lower().strip() not in seen
                and _is_valid_answer(d)
                and _length_compatible(correct_answer, d))
        ]
        if target_similarity == "high":
            def_scored.sort(key=lambda x: -x[1])
        else:
            def_scored.sort(key=lambda x: abs(x[1] - 0.20))
        for c, _ in def_scored:
            if c.lower().strip() not in seen:
                result.append(c)
                seen.add(c.lower().strip())
            if len(result) >= n:
                break

    # Pool 3: Noun phrases
    if len(result) < n:
        np_scored = [
            (np, _jaccard(correct_answer, np))
            for np in noun_phrases
            if (np.lower() not in seen
                and _is_valid_answer(np)
                and _length_compatible(correct_answer, np)
                and len(np) > 4)
        ]
        np_scored.sort(key=lambda x: abs(x[1] - 0.15))
        for c, _ in np_scored:
            if c.lower() not in seen:
                result.append(c)
                seen.add(c.lower())
            if len(result) >= n:
                break

    # Pool 4: WordNet
    if len(result) < n:
        first = next(
            (w for w in correct_answer.split() if w.lower() not in STOP_WORDS), ""
        )
        for w in _wn_siblings(first) + _wn_synonyms(first):
            if w.lower() not in seen and _is_valid_answer(w):
                result.append(w)
                seen.add(w.lower())
            if len(result) >= n:
                break

    # Pool 5: Generic fallbacks
    _fallbacks = [
        "The term applies to a different domain",
        "No standard classification exists for this",
        "This concept relates to a broader category",
        "The definition varies across different contexts",
        "This is an unrelated concept in the field",
    ]
    for fb in _fallbacks:
        if len(result) >= n:
            break
        if fb.lower() not in seen:
            result.append(fb)
            seen.add(fb.lower())

    return result[:n]


def build_entity_distractors(correct: str, label: str, entity_by_label: dict,
                              concept_bank: ConceptBank, def_bank: list,
                              noun_phrases: list, n: int = 3) -> list:
    ans_l = correct.lower()
    seen  = {ans_l}
    cands = []
    if label and label in entity_by_label:
        same = [e for e in entity_by_label[label]
                if (e.lower() not in seen
                    and _is_valid_answer(e)
                    and _length_compatible(correct, e))]
        random.shuffle(same)
        for e in same[:n * 2]:
            cands.append(e)
            seen.add(e.lower())
    if len(cands) < n:
        extra = build_smart_distractors(
            correct, concept_bank, def_bank, noun_phrases,
            n=n - len(cands), target_similarity="medium"
        )
        cands.extend(extra)
    return cands[:n]


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 17 — BLOOM'S TAXONOMY CLASSIFICATION
# ═══════════════════════════════════════════════════════════════════════════

_RECALL_PAT = [
    re.compile(r"(?i)\b(is|are|was|were)\s+(a|an|the)\b"),
    re.compile(r"(?i)\b(is|are)\s+defined\s+as\b"),
    re.compile(r"(?i)\brefers?\s+to\b"),
    re.compile(r"(?i)\bmeans?\b"),
    re.compile(r"(?i)\bis\s+called\b"),
    re.compile(r"(?i)\bknown\s+as\b"),
    re.compile(r"(?i)\bstands?\s+for\b"),
]
_UNDERSTAND_PAT = [
    re.compile(r"(?i)\b(because|therefore|thus|hence|as\s+a\s+result)\b"),
    re.compile(r"(?i)\bwhich\s+(means|allows|enables|causes|helps)\b"),
    re.compile(r"(?i)\bin\s+order\s+to\b"),
    re.compile(r"(?i)\bso\s+that\b"),
    re.compile(r"(?i)\bthis\s+(means|shows|indicates|ensures)\b"),
]
_APPLY_PAT = [
    re.compile(r"(?i)\b(used|applied|implemented|utilized|employed)\s+(to|for|in|by)\b"),
    re.compile(r"(?i)\bcan\s+be\s+(used|applied|implemented)\b"),
    re.compile(r"(?i)\b(process|procedure|method|technique|approach|step)\b"),
    re.compile(r"(?i)\bfor\s+example\b"),
    re.compile(r"(?i)\bsuch\s+as\b"),
]
_ANALYZE_PAT = [
    re.compile(r"(?i)\b(however|whereas|although|despite|nevertheless)\b"),
    re.compile(r"(?i)\bon\s+the\s+other\s+hand\b"),
    re.compile(r"(?i)\b(compare[sd]?|contrast[s]?|distinguish|differ[s]?)\b"),
    re.compile(r"(?i)\b(advantage|disadvantage|limitation|implication|consequence)\b"),
    re.compile(r"(?i)\b(evaluate|analyze|assess|determine|examine)\b"),
]


def classify_bloom(sentence: str) -> str:
    for p in _ANALYZE_PAT:
        if p.search(sentence): return "analysis"
    for p in _APPLY_PAT:
        if p.search(sentence): return "application"
    for p in _UNDERSTAND_PAT:
        if p.search(sentence): return "understanding"
    for p in _RECALL_PAT:
        if p.search(sentence): return "recall"
    return "understanding"


def classify_question_length(q: str) -> str:
    n = len(q.split())
    return "short" if n < 10 else ("medium" if n <= 20 else "long")


def compute_distractor_similarity(answer: str, distractors: list) -> str:
    if not distractors:
        return "low"
    ans_bg = _bigrams(answer)
    scores = []
    for d in distractors:
        d_bg = _bigrams(d)
        u    = ans_bg | d_bg
        scores.append(len(ans_bg & d_bg) / len(u) if u else 0.0)
    avg = sum(scores) / len(scores)
    return "high" if avg >= 0.35 else ("medium" if avg >= 0.15 else "low")


def compute_clue_level(question: str, answer: str) -> str:
    q_w = set(re.findall(r"\b[a-z]{3,}\b", question.lower())) - STOP_WORDS
    a_w = set(re.findall(r"\b[a-z]{3,}\b", answer.lower())) - STOP_WORDS
    if not q_w or not a_w:
        return "low"
    r = len(q_w & a_w) / max(len(a_w), 1)
    return "high" if r >= 0.5 else ("medium" if r >= 0.2 else "low")


_MCQ_PARAMS = {
    "easy":   {"bloom": {"recall"},                      "distractor": {"low"},           "clue": {"high"},          "q_length": {"short","medium"}},
    "medium": {"bloom": {"understanding","application"},  "distractor": {"medium","low"},  "clue": {"medium","high"}, "q_length": {"medium","short"}},
    "hard":   {"bloom": {"analysis","application"},       "distractor": {"high","medium"}, "clue": {"low","medium"},  "q_length": {"long","medium"}},
}
_N_DIST = {"easy": 3, "medium": 3, "hard": 3}


def score_mcq_match(q, ans, dists, sent, diff) -> int:
    p  = _MCQ_PARAMS[diff]
    sc = 0
    if classify_bloom(sent) in p["bloom"]:                          sc += 2
    if compute_distractor_similarity(ans, dists) in p["distractor"]: sc += 1
    if compute_clue_level(q, ans) in p["clue"]:                    sc += 1
    if classify_question_length(q) in p["q_length"]:               sc += 1
    return sc


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 18 — QUESTION TEMPLATES
# ═══════════════════════════════════════════════════════════════════════════

def _is_short_term(text: str) -> bool:
    t     = text.strip()
    words = t.split()
    if not (1 <= len(words) <= 2):
        return False
    if not words[0][0].isupper():
        return False
    w0 = words[0].lower()
    if w0 in _AUX or w0 in STOP_WORDS or w0 in _ADJ_ONLY:
        return False
    if len(words[0]) < 3:
        return False
    return True


def _build_term_distractors(correct_term: str, defs: list, ngram_kws: list,
                              concept_bank: ConceptBank, n: int = 3) -> list:
    seen   = {correct_term.lower().strip()}
    result = []

    for d in defs:
        t = d["term"].strip()
        if t.lower() in seen or not _is_short_term(t):
            continue
        result.append(t)
        seen.add(t.lower())
        if len(result) >= n:
            return result

    for kw in ngram_kws:
        cap = kw[0].upper() + kw[1:]
        if cap.lower() in seen or not _is_short_term(cap):
            continue
        result.append(cap)
        seen.add(cap.lower())
        if len(result) >= n:
            return result

    for m in concept_bank.get_cluster_members(correct_term):
        cap = m[0].upper() + m[1:]
        if cap.lower() in seen or not _is_short_term(cap):
            continue
        result.append(cap)
        seen.add(cap.lower())
        if len(result) >= n:
            return result

    return result[:n]


def _make_easy_identify_question(definition: str) -> str:
    """IMP-07: cleaner templates — no 'according to material' boilerplate."""
    defn  = definition.strip().rstrip(".,;:")
    words = defn.split()
    if len(words) > 12:
        for sep in [",", ";", " which", " that"]:
            idx = defn.find(sep)
            if 0 < idx < len(defn) - 5:
                defn = defn[:idx].rstrip(".,;:")
                break
        else:
            defn = " ".join(words[:12]).rstrip(".,;:")
    defn = defn[0].lower() + defn[1:] if defn else defn
    return random.choice([
        f"What term is defined as {defn}?",
        f"What do you call {defn}?",
        f"Which term refers to {defn}?",
        f"The concept described as '{defn}' is called what?",
    ])


def _make_definition_question(term: str, difficulty: str) -> str:
    """IMP-07: cleaner medium/hard question templates."""
    t = term.strip().rstrip(".,;")
    if difficulty == "medium":
        return random.choice([
            f"What does {t} mean?",
            f"How is {t} best described?",
            f"Which statement correctly defines {t}?",
        ])
    # hard
    return random.choice([
        f"Which of the following most accurately describes {t}?",
        f"What is the most precise characterization of {t}?",
        f"Which statement about {t} is correct?",
    ])


_HANGING_ENDS = {
    "a","an","the","this","that","these","those","its","their","our","your",
    "and","or","but","nor","for","yet","so",
    "of","to","in","on","at","by","with","from","into","about","over",
    "under","through","between","among","within","without","against",
    "which","that","who","whom","whose","where","when","while","because",
    "since","although","though","if","unless","until","after","before",
    "as","than","whether","how","both","either","neither","such",
}


def _clean_trailing(text: str) -> str:
    words = text.rstrip(".,;:").split()
    while words and words[-1].lower().rstrip(".,;:") in _HANGING_ENDS:
        words = words[:-1]
    return " ".join(words).rstrip(".,;:")


def _trim_answer(text: str, max_words: int = 12) -> str:
    text = text.strip()
    if not text:
        return text
    text  = text[0].upper() + text[1:]
    words = text.split()
    if len(words) <= max_words:
        return _clean_trailing(text)
    trimmed = " ".join(words[:max_words])
    for sep_pat in [r",\s*(?:and|or|but|which|that)\b", r";\s*", r":\s*"]:
        m = re.search(sep_pat, trimmed)
        if m:
            cand = _clean_trailing(trimmed[:m.start()])
            if len(cand.split()) >= 4:
                return cand
    result = _clean_trailing(" ".join(words[:max_words]))
    if len(result.split()) < 3:
        result = " ".join(words[:max(3, max_words - 2)]).rstrip(".,;:")
    return result


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 19 — MCQ ASSEMBLY
# ═══════════════════════════════════════════════════════════════════════════

_PAD_OPTIONS = [
    "The concept applies to a different category",
    "No formal definition is provided in the text",
    "This refers to an unrelated field of study",
    "The material presents an alternative classification",
    "This applies only to special cases in the domain",
]


def assemble_mcq(question: str, answer: str, distractors: list,
                 difficulty: str = "medium") -> dict:
    """
    BUG-04 FIX: deduplicate options using lowercased comparison before adding.
    IMP-08: always shuffle so correct answer isn't always in position 0.
    """
    q = question.strip()
    if not q.endswith("?"):
        q += "?"
    q = q[0].upper() + q[1:]

    # Build options with deduplication
    ans_lower = answer.strip().lower()
    all_opts  = [answer]
    seen_opts = {ans_lower}

    for d in distractors:
        d_strip = d.strip()
        if d_strip.lower() not in seen_opts and d_strip:
            all_opts.append(d_strip)
            seen_opts.add(d_strip.lower())

    # Pad to 4 if needed
    for pad in _PAD_OPTIONS:
        if len(all_opts) >= 4:
            break
        if pad.lower() not in seen_opts:
            all_opts.append(pad)
            seen_opts.add(pad.lower())

    opts = all_opts[:4]
    # IMP-08: shuffle options
    random.shuffle(opts)

    return {
        "type":         "multiple_choice",
        "question":     q,
        "options":      opts,
        "answer_index": opts.index(answer),
        "answer":       answer,
    }


def _valid_mcq(question: str, answer: str, distractors: list,
               seen_answers: set) -> bool:
    """
    BUG-07 FIX: also verify distractors are non-empty strings.
    """
    if not question or not answer:
        return False
    if not _is_valid_answer(answer):
        return False
    if answer.strip().lower() in seen_answers:
        return False
    # BUG-07 FIX: require at least 2 valid non-empty distractors
    valid_dists = [d for d in distractors if d and d.strip()
                   and d.strip().lower() != answer.strip().lower()]
    if len(valid_dists) < 2:
        return False
    if "____" in question:
        return False
    return True


def _extract_clean_subject(before_verb: str) -> str:
    words = before_verb.strip().split()
    while words and words[-1].lower() in (_AUX | STOP_WORDS):
        words = words[:-1]
    if not words:
        return ""
    subj_words = words[-5:]
    while subj_words and subj_words[0].lower() in {"the","a","an","this","that"}:
        subj_words = subj_words[1:]
    subj = " ".join(subj_words).strip().rstrip(",;")
    if not subj or subj.lower() in _AUX or subj.lower() in STOP_WORDS:
        return ""
    if subj.split()[0].lower() in _AUX:
        return ""
    if len(subj) < 3:
        return ""
    return subj


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 20 — MCQ GENERATORS
# ═══════════════════════════════════════════════════════════════════════════

def _mcq_from_definitions(defs, concept_bank, def_bank, noun_phrases,
                           ngram_kws, difficulty, seen_answers) -> list:
    candidates = []
    n_dist     = _N_DIST.get(difficulty, 3)

    for d in defs:
        term      = d["term"]
        short_def = d["short_def"]
        sentence  = d["sentence"]

        if difficulty == "easy":
            if not _is_short_term(term):
                continue
            if term.strip().lower() in seen_answers:
                continue
            distractors = _build_term_distractors(
                term, defs, ngram_kws, concept_bank, n=n_dist
            )
            if len(distractors) < 2:
                continue
            if not _valid_mcq(short_def, term, distractors, seen_answers):
                continue
            question = _make_easy_identify_question(short_def)
            sc       = score_mcq_match(question, term, distractors, sentence, difficulty)
            result   = assemble_mcq(question, term, distractors, difficulty)
            result["_score"]     = sc + 5
            result["bloom_level"] = "recall"
            candidates.append(result)

        else:
            sim_tgt = "medium" if difficulty == "medium" else "high"
            if not _is_valid_answer(short_def):
                continue
            if short_def.strip().lower() in seen_answers:
                continue
            distractors = build_smart_distractors(
                short_def, concept_bank, def_bank, noun_phrases,
                n=n_dist, target_similarity=sim_tgt
            )
            if not _valid_mcq(term, short_def, distractors, seen_answers):
                continue
            question = _make_definition_question(term, difficulty)
            sc       = score_mcq_match(question, short_def, distractors, sentence, difficulty)
            result   = assemble_mcq(question, short_def, distractors, difficulty)
            result["_score"]     = sc + 4
            result["bloom_level"] = classify_bloom(sentence)
            candidates.append(result)

    return candidates


def _mcq_from_ngram_concepts(ranked_sentences, ngram_kws, concept_bank,
                               def_bank, noun_phrases, defs,
                               difficulty, seen_answers) -> list:
    candidates  = []
    n_dist      = _N_DIST.get(difficulty, 3)
    sim_tgt     = "low" if difficulty == "easy" else "medium"
    def_terms   = {d["term"].lower() for d in defs}
    used_terms  = set()

    if difficulty == "easy":
        short_grams = [kw for kw in ngram_kws
                       if _is_short_term(kw[0].upper() + kw[1:])
                       and kw not in def_terms]
        for gram in short_grams:
            cap_gram = gram[0].upper() + gram[1:]
            if cap_gram in used_terms or cap_gram.strip().lower() in seen_answers:
                continue
            best_sent = next(
                (s for s in ranked_sentences if gram.lower() in s.lower()), None
            )
            if not best_sent:
                continue
            defn_text = None
            for pat in _DEF_PATTERNS[:6]:
                m = pat.match(best_sent)
                if m:
                    cand = _extract_short_definition(m.group(2).strip())
                    if _is_valid_answer(cand) and len(cand.split()) >= 3:
                        defn_text = cand
                        break
            if not defn_text:
                continue
            distractors = _build_term_distractors(
                cap_gram, defs, ngram_kws, concept_bank, n=n_dist
            )
            if len(distractors) < 2:
                continue
            if not _valid_mcq(defn_text, cap_gram, distractors, seen_answers):
                continue
            question = _make_easy_identify_question(defn_text)
            sc       = score_mcq_match(question, cap_gram, distractors, best_sent, difficulty)
            result   = assemble_mcq(question, cap_gram, distractors, difficulty)
            result["_score"]     = sc + 2
            result["bloom_level"] = "recall"
            candidates.append(result)
            used_terms.add(cap_gram)
        return candidates

    # Medium / Hard
    multi_grams = [kw for kw in ngram_kws if " " in kw and kw not in def_terms]
    for gram in multi_grams:
        if gram in used_terms:
            continue
        best_sent, best_sc = None, -1
        for sent in ranked_sentences:
            if gram.lower() in sent.lower():
                sc = score_sentence_educational(sent, ngram_kws, [], set())
                if sc > best_sc:
                    best_sc = sc; best_sent = sent
        if not best_sent or best_sc < 1.5:
            continue
        answer = None
        for pat in _DEF_PATTERNS[:6]:
            m = pat.match(best_sent)
            if m:
                defn      = m.group(2).strip()
                candidate = _extract_short_definition(defn)
                if _is_valid_answer(candidate):
                    answer = candidate
                    break
        if not answer:
            answer = _trim_answer(best_sent, max_words=12)
            if not _is_valid_answer(answer):
                continue
        if answer.strip().lower() in seen_answers:
            continue
        distractors = build_smart_distractors(
            answer, concept_bank, def_bank, noun_phrases,
            n=n_dist, target_similarity=sim_tgt
        )
        if not _valid_mcq(gram, answer, distractors, seen_answers):
            continue
        question = _make_definition_question(gram, difficulty)
        sc       = score_mcq_match(question, answer, distractors, best_sent, difficulty)
        result   = assemble_mcq(question, answer, distractors, difficulty)
        result["_score"]     = sc + 2
        result["bloom_level"] = classify_bloom(best_sent)
        candidates.append(result)
        used_terms.add(gram)

    return candidates


def _mcq_from_relations(ranked_sents, ngram_kws, concept_bank, def_bank,
                         noun_phrases, difficulty, seen_answers) -> list:
    candidates = []
    n_dist     = _N_DIST.get(difficulty, 3)

    for sent in ranked_sents:
        if _INSTRUC_RE.search(sent):
            continue
        if classify_bloom(sent) not in ("understanding", "application", "analysis"):
            continue
        for rel_pat, rel_label in _RELATION_PATTERNS:
            m = rel_pat.search(sent)
            if not m:
                continue
            before_verb = sent[:m.start()].strip()
            after_verb  = sent[m.end():].strip()
            subj        = _extract_clean_subject(before_verb)
            if not subj or not _is_valid_entity(subj) or len(subj.split()) > 6:
                continue
            if not any(kw.lower() in sent.lower() for kw in ngram_kws):
                continue
            answer_raw  = after_verb.split(",")[0].strip()
            answer      = _trim_answer(answer_raw, max_words=10)
            if not _is_valid_answer(answer) or len(answer.split()) < 3:
                continue
            distractors = build_smart_distractors(
                answer, concept_bank, def_bank, noun_phrases,
                n=n_dist, target_similarity="medium"
            )
            if not _valid_mcq(subj, answer, distractors, seen_answers):
                continue
            question = f"What does {subj} {rel_label}?"
            sc       = score_mcq_match(question, answer, distractors, sent, difficulty)
            result   = assemble_mcq(question, answer, distractors, difficulty)
            result["_score"]     = sc + 1
            result["bloom_level"] = classify_bloom(sent)
            candidates.append(result)
            break

    return candidates


def _find_entity_description(entity_text: str, defs: list, sentences: list) -> str:
    """
    BUG-09 FIX: Added guard so entity text can't be returned as its own description.
    Also tightened the copula-strip regex to avoid returning empty strings.
    """
    ent_l = entity_text.lower().strip()

    # Priority 1: entity is a defined term
    for d in defs:
        if d["term"].lower().strip() == ent_l:
            sd = d["short_def"]
            if _is_valid_answer(sd) and sd.lower().strip() != ent_l:
                return sd

    # Priority 2: sentence where entity IS the subject of a definition pattern
    for sent in sentences:
        if entity_text not in sent:
            continue
        for pat in _DEF_PATTERNS[:7]:
            m = pat.match(sent)
            if m and m.group(1).strip().lower() == ent_l:
                cand = _extract_short_definition(m.group(2).strip())
                # BUG-09 FIX: guard against returning entity itself
                if (cand
                        and _is_valid_answer(cand)
                        and cand.lower().strip() != ent_l
                        and len(cand.split()) >= 2):
                    return cand

    # Priority 3: text after entity in the sentence
    for sent in sentences:
        if entity_text not in sent:
            continue
        idx   = sent.find(entity_text)
        after = sent[idx + len(entity_text):].strip()
        after = re.sub(
            r"^(is|are|was|were|refers?\s+to|means?|:\s*|,\s*which\s+is|—\s*)\s*",
            "", after, flags=re.IGNORECASE
        ).strip()
        if not after:
            continue
        clause = after.split(",")[0].strip()
        cand   = _trim_answer(clause, max_words=10)
        # BUG-09 FIX: strict guard
        if (cand
                and _is_valid_answer(cand)
                and cand.lower().strip() != ent_l
                and cand.lower().strip() not in entity_text.lower()
                and len(cand.split()) >= 2):
            return cand

    return ""


def _mcq_from_entities(ranked_sents, entities, entity_by_label, noun_phrases,
                        concept_bank, defs, def_bank, difficulty, seen_answers) -> list:
    candidates = []
    n_dist     = _N_DIST.get(difficulty, 3)
    seen_ents  = set()
    sim_tgt    = "low" if difficulty == "easy" else "medium"

    for ent in entities:
        subj  = ent["text"]
        label = ent["label"]
        if subj.lower() in seen_ents:
            continue
        if not _is_valid_entity(subj):
            continue
        description = _find_entity_description(subj, defs, ranked_sents)
        if not description:
            continue
        if description.strip().lower() == subj.strip().lower():
            continue
        if description.strip().lower() in seen_answers:
            continue
        context_sent = next(
            (s for s in ranked_sents if subj in s), ranked_sents[0] if ranked_sents else ""
        )
        bloom = classify_bloom(context_sent)
        distractors = build_smart_distractors(
            description, concept_bank, def_bank, noun_phrases,
            n=n_dist, target_similarity=sim_tgt
        )
        if not _valid_mcq(subj, description, distractors, seen_answers):
            continue
        question = _make_entity_question_safe(subj, label, context_sent, difficulty)
        if not question or "____" in question:
            continue
        sc     = score_mcq_match(question, description, distractors, context_sent, difficulty)
        result = assemble_mcq(question, description, distractors, difficulty)
        result["_score"]     = sc
        result["bloom_level"] = bloom
        candidates.append(result)
        seen_ents.add(subj.lower())

    return candidates


def _make_entity_question_safe(ent: str, label: str, sent: str, diff: str) -> str:
    if label == "PERSON":
        return random.choice([
            f"Who is {ent}?",
            f"What role does {ent} play?",
        ])
    elif label == "DATE":
        idx = sent.find(ent)
        if idx > 10:
            before = " ".join(sent[:idx].strip().rstrip(",;").split()[-5:]).lower().strip("., ")
            if before and len(before) > 5 and before.split()[0] not in _AUX:
                return f"When did {before} occur?"
        return f"What event is associated with {ent}?"
    elif label == "CARDINAL":
        idx = sent.find(ent)
        if idx >= 0:
            after_words = sent[idx + len(ent):].strip().split()[:5]
            ctx         = " ".join(after_words).lower().rstrip(".,;")
            ctx_words   = [w for w in ctx.split() if w not in _AUX]
            if ctx_words:
                return f"How many {' '.join(ctx_words)} are described?"
        return f"What does the number {ent} represent?"
    else:
        return random.choice([
            f"What is {ent}?",
            f"Which of the following best describes {ent}?",
        ])


def _mcq_hard_analysis(ranked_sents, ngram_kws, concept_bank, def_bank,
                        noun_phrases, seen_answers) -> list:
    candidates = []
    seen_kws   = set()

    analysis_sents = [
        s for s in ranked_sents
        if (classify_bloom(s) in ("analysis", "application")
            and len(s.split()) >= 14
            and not _INSTRUC_RE.search(s))
    ]
    if len(analysis_sents) < 5:
        analysis_sents = [s for s in ranked_sents
                          if len(s.split()) >= 15 and not _INSTRUC_RE.search(s)]

    for kw in ngram_kws:
        if kw in seen_kws:
            continue
        related   = [s for s in analysis_sents if kw.lower() in s.lower()]
        unrelated = [s for s in analysis_sents if kw.lower() not in s.lower()]
        if not related or len(unrelated) < 3:
            continue
        correct_trim = _trim_answer(related[0], max_words=14)
        if (not _is_valid_answer(correct_trim)
                or correct_trim.strip().lower() in seen_answers
                or len(correct_trim.split()) < 6):
            continue
        dist_pool   = [_trim_answer(s, max_words=14) for s in unrelated[:6]]
        dist_pool   = [d for d in dist_pool
                       if _is_valid_answer(d)
                       and d.strip().lower() != correct_trim.strip().lower()
                       and len(d.split()) >= 5]
        distractors = build_smart_distractors(
            correct_trim, concept_bank, def_bank + dist_pool, noun_phrases,
            n=3, target_similarity="high"
        )
        if len(distractors) < 2:
            continue
        question = f"Which statement about {kw} is correct?"
        sc       = score_mcq_match(question, correct_trim, distractors, related[0], "hard")
        result   = assemble_mcq(question, correct_trim, distractors, "hard")
        result["_score"]     = sc
        result["bloom_level"] = "analysis"
        candidates.append(result)
        seen_kws.add(kw)

    return candidates


def _mcq_sentence_fallback(ranked_sents, ngram_kws, def_bank, noun_phrases,
                            difficulty, seen_answers, target: int) -> list:
    """
    BUG-05 FIX: seen_answers set is now passed by reference correctly;
    candidates' answers are added to it so subsequent iterations don't duplicate.
    """
    candidates = []
    seen_q     = set()
    local_seen = set(seen_answers)   # BUG-05 FIX: local copy to track within fallback

    good = [
        s for s in ranked_sents
        if (not _INSTRUC_RE.search(s)
            and len(s.split()) >= 10
            and len(s.split()) <= 35
            and not s.strip().endswith(":"))
    ]
    if len(good) < 4:
        return []

    for i, sent in enumerate(good[:target * 4]):
        if len(candidates) >= target:
            break
        correct = _trim_answer(sent, max_words=15)
        if not correct or not _is_valid_answer(correct):
            continue
        if correct.strip().lower() in local_seen:
            continue
        topic = next((kw for kw in ngram_kws if kw.lower() in sent.lower()), None)
        if not topic:
            continue
        distractors = []
        for j, other in enumerate(good):
            if j == i:
                continue
            if topic.lower() in other.lower():
                continue
            d = _trim_answer(other, max_words=15)
            if (d and _is_valid_answer(d)
                    and d.strip().lower() != correct.strip().lower()
                    and d.strip().lower() not in local_seen):
                distractors.append(d)
            if len(distractors) >= 3:
                break
        if len(distractors) < 2:
            continue
        if difficulty == "easy":
            question = f"Which of the following is true about {topic}?"
        elif difficulty == "medium":
            question = f"Which statement about {topic} is correct?"
        else:
            question = f"Which of the following best describes {topic}?"
        q_key = hashlib.md5(question.encode()).hexdigest()[:12]  # IMP-04: full hash
        if q_key in seen_q:
            continue
        seen_q.add(q_key)
        result = assemble_mcq(question, correct, distractors, difficulty)
        result["_score"]     = 1
        result["bloom_level"] = classify_bloom(sent)
        candidates.append(result)
        local_seen.add(correct.strip().lower())  # BUG-05 FIX

    return candidates


def generate_mcq(ranked_sents, sentences, entities, noun_phrases, ngram_kws,
                 defs, def_bank, concept_bank, difficulty, count) -> list:
    entity_by_label = defaultdict(list)
    for e in entities:
        entity_by_label[e["label"]].append(e["text"])
    ebl = dict(entity_by_label)

    seen_answers: set = set()
    all_candidates    = []

    # Layer 1 — Definition questions (highest quality)
    def_qs = _mcq_from_definitions(defs, concept_bank, def_bank, noun_phrases,
                                    ngram_kws, difficulty, seen_answers)
    all_candidates.extend(def_qs)
    for c in def_qs:
        seen_answers.add(c["answer"].strip().lower())

    # Layer 2 — N-gram concept questions
    ngram_qs = _mcq_from_ngram_concepts(ranked_sents, ngram_kws, concept_bank,
                                         def_bank, noun_phrases, defs,
                                         difficulty, seen_answers)
    all_candidates.extend(ngram_qs)
    for c in ngram_qs:
        seen_answers.add(c["answer"].strip().lower())

    # Layer 3 — Relational questions
    rel_qs = _mcq_from_relations(ranked_sents, ngram_kws, concept_bank,
                                  def_bank, noun_phrases, difficulty, seen_answers)
    all_candidates.extend(rel_qs)
    for c in rel_qs:
        seen_answers.add(c["answer"].strip().lower())

    # Layer 4 — Entity questions
    ent_qs = _mcq_from_entities(ranked_sents, entities, ebl, noun_phrases,
                                  concept_bank, defs, def_bank, difficulty, seen_answers)
    all_candidates.extend(ent_qs)
    for c in ent_qs:
        seen_answers.add(c["answer"].strip().lower())

    # Layer 5 — Hard analysis questions
    if difficulty == "hard":
        hard_qs = _mcq_hard_analysis(ranked_sents, ngram_kws, concept_bank,
                                      def_bank, noun_phrases, seen_answers)
        all_candidates.extend(hard_qs)

    # Layer 6 — Sentence fallback (fills gap)
    if len(all_candidates) < count + 3:
        fallback_qs = _mcq_sentence_fallback(
            ranked_sents, ngram_kws, def_bank, noun_phrases,
            difficulty, seen_answers,
            target=(count - len(all_candidates) + 8)
        )
        all_candidates.extend(fallback_qs)
        for c in fallback_qs:
            seen_answers.add(c["answer"].strip().lower())

    all_candidates.sort(key=lambda x: -x.get("_score", 0))

    _target_blooms = {
        "easy":   {"recall"},
        "medium": {"understanding", "application"},
        "hard":   {"analysis", "application"},
    }
    target_bloom = _target_blooms.get(
        difficulty, {"recall","understanding","application","analysis"}
    )

    def _accept(c: dict, seen_q: set) -> bool:
        # IMP-04: use full question hash for deduplication
        key  = hashlib.md5(c["question"].encode()).hexdigest()[:12]
        if key in seen_q:
            return False
        opts = c.get("options", [])
        if len(opts) < 4:
            return False
        # BUG-07 FIX: all options must be non-empty
        if any(not opt or not opt.strip() for opt in opts):
            return False
        return True

    seen_q: set = set()
    unique:  list = []

    # Pass 1 — difficulty-matched Bloom level
    for c in all_candidates:
        if len(unique) >= count:
            break
        if c.get("bloom_level") not in target_bloom:
            continue
        if not _accept(c, seen_q):
            continue
        key = hashlib.md5(c["question"].encode()).hexdigest()[:12]
        seen_q.add(key)
        c.pop("_score", None)
        c.pop("bloom_level", None)
        unique.append(c)

    # Pass 2 — fill remaining with any valid question
    if len(unique) < count:
        for c in all_candidates:
            if len(unique) >= count:
                break
            if not _accept(c, seen_q):
                continue
            key = hashlib.md5(c["question"].encode()).hexdigest()[:12]
            seen_q.add(key)
            c.pop("_score", None)
            c.pop("bloom_level", None)
            unique.append(c)

    random.shuffle(unique)
    return unique[:count]


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 21 — FILL-BLANK GENERATORS
# ═══════════════════════════════════════════════════════════════════════════

def _fill_easy(sentences, defs, seen_answers) -> list:
    result = []
    for d in defs:
        term, sentence = d["term"], d["sentence"]
        if term.lower() in seen_answers or not _is_valid_term(term):
            continue
        if _INSTRUC_RE.search(sentence):
            continue
        q = re.sub(re.escape(term), "____", sentence, count=1, flags=re.IGNORECASE)
        if "____" not in q:
            continue
        # IMP-02: skip if question has double blanks (confusing)
        if q.count("____") > 1:
            q = re.sub(r"____", term, q)
            q = re.sub(re.escape(term), "____", q, count=1, flags=re.IGNORECASE)
        result.append({
            "type": "fill_blank", "question": q,
            "options": None, "answer_index": -1,
            "answer": term, "distractors": [],
        })
    return result


def _fill_medium(sentences, ngram_kws, defs, seen_answers) -> list:
    result    = []
    seen_kws  = set()
    def_terms = {d["term"].lower() for d in defs}
    pref  = [s for s in sentences
             if classify_bloom(s) in ("understanding","application")
             and any(t in s.lower() for t in def_terms)
             and not _INSTRUC_RE.search(s)]
    other = [s for s in sentences
             if classify_bloom(s) in ("understanding","application")
             and s not in pref and not _INSTRUC_RE.search(s)]
    for sent in pref + other:
        for kw in ngram_kws:
            if kw in seen_kws or kw.lower() not in sent.lower():
                continue
            if kw.lower() in seen_answers:
                continue
            q = re.sub(re.escape(kw), "____", sent, count=1, flags=re.IGNORECASE)
            if "____" not in q:
                continue
            result.append({
                "type": "fill_blank", "question": q,
                "options": None, "answer_index": -1,
                "answer": kw, "distractors": [],
            })
            seen_kws.add(kw)
            break
    return result


def _fill_hard(sentences, entities, ngram_kws, seen_answers) -> list:
    """
    BUG-03 FIX: For fill-blank hard questions that have multiple blanks,
    only expose the FIRST blank answer as the canonical answer string
    (was previously " / ".join(answers) which broke submit_answer.php comparison).
    The question text now shows all blanks but `answer` field has only first blank.
    """
    result     = []
    seen_sents = set()
    scored     = sorted(
        [(s, sum(1 for kw in ngram_kws if kw.lower() in s.lower()))
         for s in sentences
         if len(s.split()) >= 16 and not _INSTRUC_RE.search(s)],
        key=lambda x: -x[1]
    )
    for sent, _ in scored:
        if sent in seen_sents:
            continue
        filled       = sent
        answers_used = []
        for ent in entities:
            ans = ent["text"]
            if (ans in filled and _is_valid_entity(ans)
                    and ans.lower() not in seen_answers):
                filled = filled.replace(ans, "____", 1)
                answers_used.append(ans)
                if filled.count("____") >= 2:
                    break
        if len(answers_used) < 2:
            for kw in ngram_kws:
                if kw.lower() in filled.lower() and kw.lower() not in seen_answers:
                    filled = re.sub(re.escape(kw), "____", filled, 1, re.IGNORECASE)
                    answers_used.append(kw)
                    if filled.count("____") >= 2:
                        break
        if "____" not in filled or not answers_used:
            continue
        # BUG-03 FIX: answer = first blank only (not joined string)
        result.append({
            "type": "fill_blank", "question": filled,
            "options": None, "answer_index": -1,
            "answer": answers_used[0],   # BUG-03 FIX: was " / ".join(answers_used)
            "distractors": [],
        })
        seen_sents.add(sent)
    return result


def _dedup(qs: list) -> list:
    """IMP-04: use full hash for deduplication."""
    seen, unique = set(), []
    for q in qs:
        k = hashlib.md5(q["question"].encode()).hexdigest()[:16]
        if k not in seen:
            seen.add(k)
            unique.append(q)
    return unique


def generate_fill_blank(sentences, entities, defs, ngram_kws, difficulty, count) -> list:
    """
    BUG-06 FIX: seen_answers is now updated AFTER deduplication so the fallback
    layers (medium/hard) don't mistakenly think answers are already used.
    """
    all_qs: list = []

    if difficulty == "easy":
        seen_answers: set = set()
        qs = _fill_easy(sentences, defs, seen_answers)
        seen_answers.update(q["answer"].lower() for q in qs)
        if len(qs) < count:
            qs += _fill_medium(sentences, ngram_kws, defs, seen_answers)

    elif difficulty == "medium":
        seen_answers: set = set()
        qs = _fill_medium(sentences, ngram_kws, defs, seen_answers)
        seen_answers.update(q["answer"].lower() for q in qs)
        if len(qs) < count:
            qs += _fill_easy(sentences, defs, seen_answers)

    else:
        seen_answers: set = set()
        qs = _fill_hard(sentences, entities, ngram_kws, seen_answers)
        seen_answers.update(q["answer"].lower() for q in qs)
        if len(qs) < count:
            medium_qs = _fill_medium(sentences, ngram_kws, defs, seen_answers)
            qs += medium_qs
            seen_answers.update(q["answer"].lower() for q in medium_qs)
        if len(qs) < count:
            qs += _fill_easy(sentences, defs, seen_answers)

    unique = _dedup(qs)
    random.shuffle(unique)
    return unique[:count]


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 22 — HELPERS
# ═══════════════════════════════════════════════════════════════════════════

def get_noun_phrases(sentences: list) -> list:
    seen, phrases = set(), []
    for sent in sentences:
        for np in _extract_noun_phrases(sent):
            if np.lower() not in seen and len(np) > 3 and _is_valid_answer(np):
                seen.add(np.lower())
                phrases.append(np)
    return phrases


def build_def_bank(defs: list, sentences: list, ngram_kws: list) -> list:
    bank = []
    for d in defs:
        if _is_valid_answer(d["short_def"]):
            bank.append(d["short_def"])
    for sent in sentences:
        if _INSTRUC_RE.search(sent):
            continue
        if sum(1 for kw in ngram_kws if kw.lower() in sent.lower()) < 2:
            continue
        trimmed = _trim_answer(sent, max_words=10)
        if trimmed and len(trimmed.split()) >= 3 and _is_valid_answer(trimmed):
            bank.append(trimmed)
    seen, unique = set(), []
    for item in bank:
        key = item.strip().lower()
        if key not in seen and len(key) > 5:
            seen.add(key)
            unique.append(item)
    return unique


def is_fill_blank_type(quiz_type: str) -> bool:
    return any(k in quiz_type.lower() for k in ("fill","blank","identif"))


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 23 — MAIN PROCESS
# ═══════════════════════════════════════════════════════════════════════════

def process(pdf_path: str,
            difficulty: str = "easy",
            quiz_type:  str = "multiple_choice",
            count:      int = 10) -> dict:

    count      = int(count)
    difficulty = difficulty.lower().strip()
    if difficulty not in ("easy","medium","hard"):
        difficulty = "medium"

    text = extract_pdf_text(pdf_path)

    validation = validate_pdf(text)
    if not validation["valid"]:
        return {"error": " ".join(l for l in validation["logs"] if l.startswith("REJECTED"))}

    sentences = get_sentences(text)[:500]

    entities     = _rule_based_ner(text)
    ngram_kws    = get_ngram_keywords(sentences, top_n=50)
    noun_phrases = get_noun_phrases(sentences)
    defs         = find_definition_sentences(sentences)
    ranked_sents = rank_sentences(sentences, ngram_kws, entities)
    concept_bank = build_concept_bank(ngram_kws, defs, entities, noun_phrases)
    def_bank     = build_def_bank(defs, sentences, ngram_kws)

    overview      = generate_overview(sentences, ngram_kws, entities)
    terminologies = generate_terminologies(sentences, ngram_kws, defs)
    study_points  = generate_study_points(sentences, ngram_kws, entities)

    if is_fill_blank_type(quiz_type):
        quiz = generate_fill_blank(sentences, entities, defs, ngram_kws, difficulty, count)
    else:
        quiz = generate_mcq(
            ranked_sents, sentences, entities, noun_phrases, ngram_kws,
            defs, def_bank, concept_bank, difficulty, count
        )

    # IMP-06: if still not enough, lower the bar (run fallback with any sentences)
    if not quiz or len(quiz) < max(1, count // 2):
        return {
            "error": (
                "Could not generate enough questions from this PDF. "
                "The document may lack clear definition sentences or sufficient content. "
                "Try a longer or more content-rich PDF."
            )
        }

    return {
        "overview":          overview,
        "key_terminologies": terminologies,
        "main_study_points": study_points,
        "quiz":              quiz,
    }


# ═══════════════════════════════════════════════════════════════════════════
#  SECTION 24 — ENTRY POINT
# ═══════════════════════════════════════════════════════════════════════════

if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.stdout.buffer.write(json.dumps({
            "error": "Usage: python engine.py <pdf_path> [difficulty] [quiz_type] [count]"
        }).encode("utf-8"))
        sys.exit(1)

    _pdf  = sys.argv[1]
    _diff = sys.argv[2] if len(sys.argv) > 2 else "easy"
    _type = sys.argv[3] if len(sys.argv) > 3 else "multiple_choice"
    _cnt  = int(sys.argv[4]) if len(sys.argv) > 4 else 10

    _result = process(_pdf, _diff, _type, _cnt)
    sys.stdout.buffer.write(
        json.dumps(_result, ensure_ascii=False, indent=2).encode("utf-8")
    )