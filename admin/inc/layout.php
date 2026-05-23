<?php
declare(strict_types=1);
namespace Kontact\Admin;

// Ensure i18n helpers are available when layout is included directly.
if (!function_exists('tr')) { require_once __DIR__ . '/../localization/i18n.php'; }

// Server-side language setter to ensure immediate switch before rendering
if (isset($_GET['__setlang'])) {
  $loc = (string)$_GET['__setlang'];
  $loc = preg_replace('/[^a-zA-Z_\-]/','', $loc);
  if ($loc !== '') {
    $loc = str_replace('-', '_', $loc);
    @setcookie('locale', $loc, time()+3600*24*180, '/');
  }
  // Redirect to same path without __setlang/lang params
  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  $parts = explode('?', $uri, 2);
  $path = $parts[0];
  // Rebuild query without __setlang and lang
  $qs = [];
  if (!empty($parts[1])) {
    parse_str($parts[1], $qs);
    unset($qs['__setlang'], $qs['lang']);
  }
  $redir = $path . (empty($qs) ? '' : ('?' . http_build_query($qs)));
  header('Location: ' . $redir, true, 302);
  exit;
}


/** Minimal helpers to keep all admin pages consistent. */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Friendly label for a locale. Extend as needed. */
function kontact_locale_label(string $loc): string {
  $L = [
    'af_na'=>'Afrikaans',
    'af_za'=>'Suid-Afrika',
    'ar'=>'الأرجنتين',
    'ar_sa'=>'العربية',
    'br_fr'=>'Brezhoneg',
    'ca_es'=>'Català',
    'cy_gb'=>'Cymraeg',
    'de_ch'=>'Schweiz',
    'en_us'=>'English (US)',
    'en_gb'=>'English (UK)',
    'en_ca'=>'English (CA)',
    'fr_fr'=>'Français',
    'de_de'=>'Deutsch',
    'es_es'=>'Español',
    'es_ar'=>'Español (AR)',
    'pt_br'=>'Português (BR)',
    'pt_pt'=>'Português (PT)',
    'it_it'=>'Italiano',
    'nl_nl'=>'Nederlands',
    'nl_be'=>'Nederlands (BE)',
    'sv_se'=>'Svenska',
    'da_dk'=>'Dansk',
    'nb_no'=>'Norsk Bokmål',
    'fi_fi'=>'Suomi',
    'pl_pl'=>'Polski',
    'cs_cz'=>'Čeština',
    'sk_sk'=>'Slovenčina',
    'sl_si'=>'Slovenščina',
    'ro_ro'=>'Română',
    'hu_hu'=>'Magyar',
    'bg_bg'=>'Български',
    'el_gr'=>'Ελληνικά',
    'ru_ru'=>'Русский',
    'uk_ua'=>'Українська',
    'tr_tr'=>'Türkçe',
    'he_il'=>'עברית',
    'hi_in'=>'हिन्दी',
    'id_id'=>'Bahasa Indonesia',
    'is_is'=>'Ísland',
    'ms_my'=>'Bahasa Melayu',
    'ja_jp'=>'日本語',
    'ko_kr'=>'한국어',
    'zh_cn'=>'简体中文',
    'zh_tw'=>'繁體中文',
    'vi_vn'=>'Tiếng Việt',
    'ga_ie'=>'Gaeilge',
    'gd_gb'=>'Gàidhlig',
    'eu_es'=>'Euskara',
    'fa_ir'=>'فارسی',
    'gl_es'=>'Galego',
    'fil_ph'=>'Filipino',
    'hr_hr'=>'Hrvatski',
    'lt_lt'=>'Lietuvių',
    'lv_lv'=>'Latviešu',
    'et_ee'=>'Eesti',
    'hy_am'=>'Հայերեն',
    'ka_ge'=>'ქართული',
    'sq_al'=>'Shqip',
    'sr_rs'=>'Srpski',
    'sr_cyrl_ba'=>'Srpski',
    'sr_cyrl_rs'=>'Српски (Ћирилица)',
    'sr_latn_ba'=>'Srpski (Latinica)',
    'sr_latn_rs'=>'Српски (Latinica)',
    'sw_ke'=>'Kiswahili',
    'sw_tz'=>'Tanzania',
    'th_th'=>'แบบไทย',
    'tr_cy'=>'Kıbrıs',
    'uz_latn_uz'=>'Oʻzbekcha',
    'uz_cyrl_uz'=>'Ўзбекча',
    'mn_cyrl_mn'=>'Монгол (Кирилл)',
    'mn_mong_mn'=>'ᠮᠣᠩᠭᠣᠯ (ᠮᠣᠩᠭᠣᠯ)'
  ];
  $k = strtolower(str_replace('-', '_', $loc));
  return $L[$k] ?? strtoupper($loc);
}

/** Map locale -> flag asset code (one per line, readable). */
function kontact_locale_flag_code(string $locale): string {
  $locale = strtolower(str_replace('-', '_', $locale));
  $map = [
    'af_na' => 'af_na',
    'af_za' => 'af_za',
    'ar'    => 'ar',
    'ar_sa' => 'ar_sa',
    'bg_bg' => 'bg_bg',
    'br_fr' => 'br_fr',
    'ca_es' => 'ca_es',
    'cs_cz' => 'cs_cz',
    'cy_gb' => 'cy_gb',
    'da_dk' => 'da_dk',
    'de_ch' => 'de_ch',
    'de_de' => 'de_de',
    'el_gr' => 'el_gr',
    'en_ca' => 'en_ca',
    'en_gb' => 'en_gb',
    'en_us' => 'en_us',
    'es_ar' => 'es_ar',
    'es_es' => 'es_es',
    'et_ee' => 'et_ee',
    'eu_es' => 'eu_es',
    'fa_ir' => 'fa_ir',
    'fi_fi' => 'fi_fi',
    'fil_ph'=> 'fil_ph',
    'fr_fr' => 'fr_fr',
    'ga_ie' => 'ga_ie',
    'gd_gb' => 'gd_gb',
    'gl_es' => 'gl_es',
    'he_il' => 'he_il',
    'hi_in' => 'hi_in',
    'hr_hr' => 'hr_hr',
    'hu_hu' => 'hu_hu',
    'hy_am' => 'hy_am',
    'id_id' => 'id_id',
    'is_is' => 'is_is',
    'it_it' => 'it_it',
    'ja_jp' => 'ja_jp',
    'ka_ge' => 'ka_ge',
    'ko_kr' => 'ko_kr',
    'lt_lt' => 'lt_lt',
    'lv_lv' => 'lv_lv',
    'mn_cyrl_mn' => 'mn_cyrl_mn',
    'mn_mong_mn' => 'mn_mong_mn',
    'ms_my' => 'ms_my',
    'nb_no' => 'nb_no',
    'nl_be' => 'nl_be',
    'nl_nl' => 'nl_nl',
    'pl_pl' => 'pl_pl',
    'pt_br' => 'pt_br',
    'pt_pt' => 'pt_pt',
    'ro_ro' => 'ro_ro',
    'ru_ru' => 'ru_ru',
    'sk_sk' => 'sk_sk',
    'sl_si' => 'sl_si',
    'sq_al' => 'sq_al',
    'sr_cyrl_ba' => 'sr_cyrl_ba',
    'sr_cyrl_rs' => 'sr_cyrl_rs',
    'sr_latn_ba' => 'sr_latn_ba',
    'sr_latn_rs' => 'sr_latn_rs',
    'sr_rs' => 'sr_rs',
    'sv_se' => 'sv_se',
    'sw_ke' => 'sw_ke',
    'sw_tz' => 'sw_tz',
    'th_th' => 'th_th',
    'tr_cy' => 'tr_cy',
    'tr_tr' => 'tr_tr',
    'uk_ua' => 'uk_ua',
    'uz_cyrl_uz' => 'uz_cyrl_uz',
    'uz_latn_uz' => 'uz_latn_uz',
    'vi_vn' => 'vi_vn',
    'zh_cn' => 'zh_cn',
    'zh_tw' => 'zh_tw',
    // language-only fallbacks
    'en' => 'en_us',
    'pt' => 'pt_br',
    'zh' => 'zh_cn',
    'sr' => 'sr_rs',
    'nl' => 'nl_nl',
    'es' => 'es_es',
    'fr' => 'fr_fr',
    'de' => 'de_de',
    'it' => 'it_it',
    'ja' => 'ja_jp',
    'ko' => 'ko_kr',
    'sv' => 'sv_se',
    'da' => 'da_dk',
    'nb' => 'nb_no',
    'pl' => 'pl_pl',
    'cs' => 'cs_cz',
    'sk' => 'sk_sk',
    'sl' => 'sl_si',
    'uk' => 'uk_ua',
    'ru' => 'ru_ru',
    'fi' => 'fi_fi',
    'lv' => 'lv_lv',
    'lt' => 'lt_lt',
    'et' => 'et_ee',
    'ro' => 'ro_ro',
    'hu' => 'hu_hu',
    'tr' => 'tr_tr',
    'vi' => 'vi_vn',
    'ar' => 'ar',
    'he' => 'he_il',
    'ms' => 'ms_my',
    'id' => 'id_id',
    'hr' => 'hr_hr',
    'bg' => 'bg_bg',
    'el' => 'el_gr',
    'ga' => 'ga_ie',
    'gd' => 'gd_gb',
    'gl' => 'gl_es',
    'eu' => 'eu_es',
    'br' => 'br_fr',
    'sq' => 'sq_al',
    'uz' => 'uz_latn_uz',
    'mn_cyrl' => 'mn_cyrl_mn',
    'mn_mong' => 'mn_mong_mn'
  ];
  if (isset($map[$locale])) { return $map[$locale]; }
  $lang = substr($locale, 0, 2);
  if (isset($map[$lang])) { return $map[$lang]; }
  return 'globe';
}

function header_nav(string $title, string $active='dashboard'): void { ?>
<!doctype html>
<html lang="<?php echo htmlspecialchars(function_exists('kontact_current_locale') ? (string)kontact_current_locale() : 'en_US', ENT_QUOTES, 'UTF-8'); ?>">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<link rel="stylesheet" href="../assets/css/admin.css"/>
<title><?php echo h($title); ?> • Kontact</title>
<?php
  $__locale = strtolower((string)(function_exists('kontact_current_locale') ? kontact_current_locale() : 'en_US'));
  $__flag   = kontact_locale_flag_code(str_replace('-', '_', $__locale));
  $supported = function_exists('kontact_supported_locales') ? kontact_supported_locales() : [];
  $__i18n = [
    'copied'        => tr('copied','Copied!'),
    'error'         => tr('error','Error'),
    'done'          => tr('done','Done.'),
    'indexes_added' => tr('indexes_added','Indexes added:'),
    'notes'         => tr('notes','Notes:')
  ];
?>
<script>
window.I18N = Object.assign(window.I18N||{}, <?php echo json_encode($__i18n, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>);
window.tr = function(k, d){ return (window.I18N && window.I18N[k]) || d || k; };
</script>
<script src="../assets/js/table_sort.js"></script>
<script src="../assets/js/clipboard.js"></script>
<script src="../assets/js/mobile_nav.js" defer></script>
</head>
<body>

<header class="header">
  <div class="header-flag" id="lang-switch">
    <span class="lang-flag" id="lang-flag-trigger" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false" title="<?php echo h(kontact_locale_label($__locale)); ?>">
      <img class="flag" src="../assets/flags/<?php echo htmlspecialchars($__flag, ENT_QUOTES, 'UTF-8'); ?>.svg" alt="<?php echo htmlspecialchars($__locale, ENT_QUOTES, 'UTF-8'); ?>">
    </span>
    <?php if (!empty($supported)): ?>
    <div class="lang-menu" id="lang-menu" hidden>
      <ul role="listbox" aria-label="Languages">
        <?php
        foreach ($supported as $loc):
          $locNorm   = strtolower(str_replace('-', '_', (string)$loc));
          $flag      = kontact_locale_flag_code($locNorm);
          $label     = kontact_locale_label($locNorm);
          $isCurrent = ($locNorm === $__locale);
        ?>
          <li role="option" aria-selected="<?php echo $isCurrent ? 'true' : 'false'; ?>">
            <button type="button" class="lang-item" data-locale="<?php echo h($loc); ?>" <?php echo $isCurrent ? 'aria-current="true"' : ''; ?>>
              <span class="lang-flag">
                <img class="flag"
                     src="../assets/flags/<?php echo h($flag); ?>.svg"
                     alt="<?php echo h($loc); ?>"
                     width="32" height="32">
              </span>
              <span class="label"><?php echo h($label); ?></span>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>

  <h1><?php echo h($title); ?></h1>
  <button class="menu-toggle" id="menu-toggle" aria-expanded="false" aria-controls="admin-nav" aria-label="Menu">&#9776;</button>
  <nav id="admin-nav">
    <a href="./index.php" class="glass-button <?php echo ($active==='dashboard' ? 'active' : ''); ?>"><?php _e('dashboard','Dashboard'); ?></a>
    <a href="messages.php" class="glass-button <?php echo ($active==='messages' ? 'active' : ''); ?>"><?php _e('messages','Messages'); ?></a>
    <a href="settings.php" class="glass-button <?php echo ($active==='settings' ? 'active' : ''); ?>"><?php _e('settings','Settings'); ?></a>
    <a href="security.php" class="glass-button <?php echo ($active==='security' ? 'active' : ''); ?>"><?php _e('security','Security'); ?></a>
    <a href="templates.php" class="glass-button <?php echo ($active==='templates' ? 'active' : ''); ?>"><?php _e('templates','Templates'); ?></a>
    <a href="users.php" class="glass-button <?php echo ($active==='users' ? 'active' : ''); ?>"><?php _e('users','Users'); ?></a>
    <a href="logs.php" class="glass-button <?php echo ($active==='logs' ? 'active' : ''); ?>"><?php _e('logs','Logs'); ?></a>
    <a href="maintenance.php" class="glass-button <?php echo ($active==='maintenance' ? 'active' : ''); ?>"><?php _e('maintenance','Maintenance'); ?></a>
    <a href="test_smtp.php" class="glass-button <?php echo ($active==='smtp' ? 'active' : ''); ?>"><?php _e('smtp_test','SMTP Test'); ?></a>
    <a href="logout.php" class="danger">Logout</a>
  </nav>
</header>

<div class="header-spacer"></div>
<div class="container">
<?php } // end header_nav ?>

<?php
function footer(): void { ?>
</div>
<script>
// Language switcher interactions with viewport-constrained menu.
(function(){
  try {
    var root = document.getElementById('lang-switch');
    if (!root) return;
    var trigger = document.getElementById('lang-flag-trigger');
    var menu = document.getElementById('lang-menu');
    if (!trigger || !menu) return;

    function openMenu(){
      menu.hidden = false;
      trigger.setAttribute('aria-expanded','true');
      try {
        var rect = root.getBoundingClientRect();
        var avail = window.innerHeight - rect.bottom - 16;
        if (avail < 180) { avail = window.innerHeight - rect.top - 16; }
        menu.style.maxHeight = Math.max(180, avail) + 'px';
        menu.style.overflowY = 'auto';
        menu.setAttribute('tabindex','-1');
        menu.focus({preventScroll:true});
      } catch(e){}
    }
    function closeMenu(){
      menu.hidden = true;
      trigger.setAttribute('aria-expanded','false');
    }

    trigger.addEventListener('click', function(ev){
      ev.preventDefault();
      ev.stopPropagation();
      ev.preventDefault();
      if (menu.hidden) openMenu(); else closeMenu();
    });
    menu.addEventListener('click', function(ev){
      var t = ev.target.closest('.lang-item');
      if (!t) return;
      ev.preventDefault();
      ev.stopPropagation();
      var loc = t.getAttribute('data-locale');
      if (!loc) return;
      // Server-side cookie then redirect, guarantees locale is set before render
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('__setlang', String(loc).replace('-', '_'));
        window.location.replace(url.toString());
      } catch(e) {
        window.location.href = window.location.href + (window.location.href.indexOf('?')>-1?'&':'?') + '__setlang=' + encodeURIComponent(loc);
      }
    });
trigger.addEventListener('keydown', function(ev){
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); if (menu.hidden) openMenu(); else closeMenu(); }
    });
    document.addEventListener('click', function(ev){
      if (!root.contains(ev.target)) closeMenu();
    });
    document.addEventListener('keydown', function(ev){
      if (ev.key === 'Escape') closeMenu();
    });
  } catch(e){}
})();

// details state persistence (per page + summary/id). Default open on first visit.
(function(){
  try {
    var els = document.querySelectorAll('details');
    els.forEach(function(el){
      var summary = el.querySelector('summary');
      var keyBase = location.pathname;
      var ident = el.id && el.id.trim() ? el.id.trim()
                 : (summary && summary.textContent ? summary.textContent.trim().toLowerCase().replace(/\s+/g,'-').slice(0,80) : 'section');
      var key = 'kontact:details:' + keyBase + ':' + ident;
      var stored = localStorage.getItem(key);
      if (stored === null) {
        // first time: default open
        el.open = true;
      } else {
        el.open = (stored === '1');
      }
      el.addEventListener('toggle', function(){
        try { localStorage.setItem(key, el.open ? '1' : '0'); } catch(e){}
      });
    });
  } catch(e) {}
})();
</script>
</body>
</html>
<?php } // end footer ?>
