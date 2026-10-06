<?php
/**
 * Language Labels: language badges over item thumbnails on browse pages.
 *
 * Badges are printed inline by the per-item browse hook; language-labels.js then
 * moves them over the item's thumbnail. Without JS, or without an image, they
 * stay inline.
 *
 * @copyright Raphael Halff
 * @license GPLv3 http://www.gnu.org/licenses/gpl-3.0.txt
 */

class LanguageLabelsPlugin extends Omeka_Plugin_AbstractPlugin
{
    /** Cache-buster for JS/CSS. */
    const ASSET_VERSION = '1.0.0';

    /** Separators between languages within one element text. */
    const SPLIT_RE = '/\s*[;,|]\s*/u';

    /** Language tag such as "en", "eng", "en-US". */
    const CODE_RE = '/^([a-z]{2,3})(?:[-_]([a-z0-9]{2,8}))?$/i';

    /** Hex, or var(--name) with an optional hex fallback. */
    const BASE_COLOUR_RE = '/^(?:#(?:[0-9a-f]{3}|[0-9a-f]{6})|var\(\s*--[a-z0-9_-]+\s*(?:,\s*#(?:[0-9a-f]{3}|[0-9a-f]{6})\s*)?\))$/i';

    const DEFAULT_BASE_HEX = '#76d7c4';

    /** Minimum hue distance (degrees) between the base language and any other. */
    const BASE_HUE_GAP = 30;

    /** Golden ratio conjugate, for evenly spread hues. */
    const GOLDEN = 0.6180339887;

    protected $_hooks = array(
        'install',
        'uninstall',
        'upgrade',
        'initialize',
        'public_head',
        'admin_head',
        'public_items_browse_each',
        'admin_items_browse_simple_each',
        'config_form',
        'config',
    );

    protected $_options = array(
        'language_labels_element_id' => 0,          // Set to Dublin Core:Language on install.
        'language_labels_display' => 'code',        // code | name | raw
        'language_labels_position' => 'top-left',
        'language_labels_max' => 3,                 // 0 = no limit.
        'language_labels_auto_colour' => 1,
        'language_labels_base_colour' => '#76D7C4',
        'language_labels_base_language' => '',
        'language_labels_colour_order' => 'en, fr, de, es, it, pt, ru, zh, ar, ja',
        'language_labels_background' => '#1f2933',  // Used when auto colour is off.
        'language_labels_foreground' => '#ffffff',
        'language_labels_custom_map' => '',
        'language_labels_show_admin' => 0,
    );

    protected $_checkboxOptions = array(
        'language_labels_auto_colour',
        'language_labels_show_admin',
    );

    protected $_positions = array(
        'top-left', 'top-right', 'bottom-left', 'bottom-right', 'auto-top', 'auto-bottom',
    );

    protected $_displays = array('code', 'name', 'raw');

    /** @var LanguageLabelsPlugin|null */
    protected static $_instance = null;

    // Per-request caches.
    protected $_element = null;
    protected $_index = null;
    protected $_customMap = null;
    protected $_baseKey = null;
    protected $_colourSlots = null;

    public function setUp()
    {
        parent::setUp();
        self::$_instance = $this;
    }

    /**
     * @return LanguageLabelsPlugin|null
     */
    public static function getInstance()
    {
        return self::$_instance;
    }

    public function hookInitialize()
    {
        $languages = dirname(__FILE__) . '/languages';
        if (is_dir($languages)) {
            add_translation_source($languages);
        }
    }

    public function hookInstall()
    {
        $this->_options['language_labels_element_id'] = $this->_findDefaultElementId();
        $this->_installOptions();
    }

    public function hookUninstall()
    {
        $this->_uninstallOptions();
    }

    public function hookUpgrade($args)
    {
        foreach ($this->_options as $key => $default) {
            if (get_option($key) === null) {
                set_option($key, $default);
            }
        }
    }

    public function hookConfigForm()
    {
        echo get_view()->partial('plugins/language-labels-config-form.php', array(
            'elementOptions' => get_table_options('Element', null, array(
                'record_types' => array('Item', 'All'),
                'sort' => 'orderBySet',
            )),
        ));
    }

    public function hookConfig($args)
    {
        $post = $args['post'];
        foreach ($this->_options as $key => $default) {
            if (isset($post[$key])) {
                set_option($key, $this->_sanitiseOption($key, $post[$key], $default));
            }
        }
        // Unchecked checkboxes are absent from the POST.
        foreach ($this->_checkboxOptions as $key) {
            if (!isset($post[$key])) {
                set_option($key, 0);
            }
        }
    }

    public function hookPublicHead($args)
    {
        $this->_queueAssets();
    }

    public function hookAdminHead($args)
    {
        if (!get_option('language_labels_show_admin')) {
            return;
        }
        $request = Zend_Controller_Front::getInstance()->getRequest();
        if ($request
            && $request->getControllerName() === 'items'
            && $request->getActionName() === 'browse'
        ) {
            $this->_queueAssets();
        }
    }

    public function hookPublicItemsBrowseEach($args)
    {
        echo $this->render($args['item'], isset($args['view']) ? $args['view'] : null);
    }

    public function hookAdminItemsBrowseSimpleEach($args)
    {
        if (get_option('language_labels_show_admin')) {
            echo $this->render($args['item'], isset($args['view']) ? $args['view'] : null);
        }
    }

    /**
     * Badge markup for an item. Always outputs a list (hidden when empty): the
     * script counts lists to tell whether a wrapper holds exactly one item.
     *
     * @param Item $item
     * @param Zend_View|null $view
     * @return string
     */
    public function render($item, $view = null)
    {
        if (!$item) {
            return '';
        }
        if (!$view) {
            $view = get_view();
        }
        return $view->partial('common/language-labels.php', array(
            'item'       => $item,
            'labels'     => $this->getLabels($item),
            'position'   => get_option('language_labels_position'),
            'autoColour' => (bool) get_option('language_labels_auto_colour'),
        ));
    }

    /**
     * De-duplicated labels for an item; anything past the maximum is folded
     * into a final "+N" label.
     *
     * Each label has: text, title (full name), key and offset (hue degrees from
     * the base colour, null for "+N").
     *
     * @param Item $item
     * @return array
     */
    public function getLabels($item)
    {
        $element = $this->_getElement();
        if (!$element) {
            return array();
        }

        $labels = array();
        foreach ($item->getElementTextsByRecord($element) as $elementText) {
            $text = $elementText->html ? strip_tags($elementText->text) : $elementText->text;
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            foreach (preg_split(self::SPLIT_RE, $text, -1, PREG_SPLIT_NO_EMPTY) as $value) {
                $label = $this->_resolve($value);
                if ($label && !isset($labels[$label['key']])) {
                    $labels[$label['key']] = $label;
                }
            }
        }
        $labels = array_values($labels);

        $max = (int) get_option('language_labels_max');
        if ($max > 0 && count($labels) > $max) {
            $rest = array_splice($labels, $max);
            $titles = array();
            foreach ($rest as $label) {
                $titles[] = $label['title'];
            }
            $labels[] = array(
                'text'   => '+' . count($rest),
                'title'  => implode(', ', $titles),
                'key'    => 'more',
                'offset' => null,
                'more'   => true,
            );
        }

        return $labels;
    }

    /**
     * Resolve one raw value to a label, or null to hide it. Custom labels win;
     * otherwise the built-in table is matched by code or name. Unknown values
     * are shown as entered.
     *
     * @param string $value
     * @return array|null
     */
    protected function _resolve($value)
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        if ($value === '') {
            return null;
        }
        $lower = $this->_lower($value);
        $index = $this->_getIndex();

        // Custom labels are keyed by language code where known, so one entry
        // covers every alias of that language.
        $custom = $this->_getCustomMap();
        $customKey = isset($index[$lower]) ? $index[$lower]['code'] : $lower;
        if (array_key_exists($customKey, $custom)) {
            if ($custom[$customKey] === '') {
                return null;
            }
            $key = isset($index[$lower]) ? $customKey : 'custom:' . $lower;
            return $this->_label($custom[$customKey], $custom[$customKey], $key);
        }

        $info = null;
        $region = null;
        if (isset($index[$lower])) {
            $info = $index[$lower];
        } elseif (preg_match(self::CODE_RE, $value, $m) && isset($index[strtolower($m[1])])) {
            // Known language with a subtag, e.g. "en-GB".
            $info = $index[strtolower($m[1])];
            $region = isset($m[2]) ? $m[2] : null;
        }

        $display = get_option('language_labels_display');

        if (!$info) {
            $text = ($display === 'code' && preg_match(self::CODE_RE, $value))
                ? strtoupper($value)
                : $value;
            return $this->_label($text, $value, $lower);
        }

        $code = $info['code'];
        $name = $info['name'];
        if ($region !== null) {
            $code .= '-' . $region;
            $name .= ' (' . strtoupper($region) . ')';
        }

        switch ($display) {
            case 'name':
                $text = $name;
                break;
            case 'raw':
                $text = $value;
                break;
            default:
                $text = strtoupper($code);
        }

        return $this->_label($text, $name, strtolower($code));
    }

    /**
     * Build a label with its hue offset. The base language gets 0; others are
     * spread by golden-ratio steps, at least BASE_HUE_GAP degrees from the base.
     * CSS turns the offset into a colour.
     */
    protected function _label($text, $title, $key)
    {
        if ($key === $this->_getBaseKey()) {
            $offset = 0;
        } else {
            $slots = $this->_getColourSlots();
            $fraction = isset($slots[$key])
                ? fmod($slots[$key] * self::GOLDEN, 1)
                : (abs(crc32($key)) % 1000) / 1000;
            $spread = 360 - 2 * self::BASE_HUE_GAP;
            $offset = self::BASE_HUE_GAP + (int) round($fraction * $spread);
        }

        return array(
            'text'   => $text,
            'title'  => $title,
            'key'    => $key,
            'offset' => $offset,
        );
    }

    /**
     * Language code => colour slot: the colour order option first, then the
     * rest of the built-in table.
     *
     * @return array
     */
    protected function _getColourSlots()
    {
        if ($this->_colourSlots !== null) {
            return $this->_colourSlots;
        }

        $order = get_option('language_labels_colour_order');
        if ($order === null) {
            $order = $this->_options['language_labels_colour_order'];
        }

        $index = $this->_getIndex();
        $codes = array();
        foreach (preg_split(self::SPLIT_RE, $order, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $lower = $this->_lower(trim($value));
            $codes[] = isset($index[$lower]) ? $index[$lower]['code'] : $lower;
        }
        foreach ($index as $info) {
            $codes[] = $info['code'];
        }

        $this->_colourSlots = array();
        foreach (array_unique($codes) as $code) {
            if ($code !== $this->_getBaseKey()) {
                $this->_colourSlots[$code] = count($this->_colourSlots) + 1;
            }
        }
        return $this->_colourSlots;
    }

    /**
     * The base language option as a label key ("yid" and "Yiddish" -> "yi").
     *
     * @return string
     */
    protected function _getBaseKey()
    {
        if ($this->_baseKey === null) {
            $lower = $this->_lower(trim((string) get_option('language_labels_base_language')));
            $index = $this->_getIndex();
            $this->_baseKey = isset($index[$lower]) ? $index[$lower]['code'] : $lower;
        }
        return $this->_baseKey;
    }

    protected function _queueAssets()
    {
        $config = array(
            'containers' => apply_filters('language_labels_containers', array(
                '.item', '.record', '.hentry', 'tr', 'article', 'li',
            )),
            'images' => apply_filters('language_labels_images', array(
                '.item-img img',
                '.item-thumbnail img',
                'img.item-thumbnail',
                '.image img',
                'a img',
                'img',
            )),
        );

        queue_js_string('window.LanguageLabels = ' . json_encode($config) . ';');
        queue_js_file('language-labels', 'javascripts', array(), self::ASSET_VERSION);
        queue_css_file('language-labels', 'all', false, 'css', self::ASSET_VERSION);

        if (get_option('language_labels_auto_colour')) {
            queue_css_string($this->_baseColourCss());
        } else {
            $bg = $this->_hex(get_option('language_labels_background'), '#1f2933');
            $fg = $this->_hex(get_option('language_labels_foreground'), '#ffffff');
            queue_css_string(":root{--language-label-bg:$bg;--language-label-fg:$fg}");
        }
    }

    /**
     * Base colour custom properties, set on .language-labels so theme variables
     * defined below :root still resolve. H/S/L come from the hex (or the var()
     * fallback hex) for browsers without relative colour syntax.
     *
     * @return string
     */
    protected function _baseColourCss()
    {
        $base = trim((string) get_option('language_labels_base_colour'));
        if (!preg_match(self::BASE_COLOUR_RE, $base)) {
            $base = self::DEFAULT_BASE_HEX;
        }

        $hex = preg_match('/#[0-9a-f]{3,6}/i', $base, $m) ? $m[0] : self::DEFAULT_BASE_HEX;
        list($h, $s, $l) = $this->_hexToHsl($hex);
        $fgLightness = $l > 55 ? 16 : 100; // Dark text on light bases, white on dark.

        return ".language-labels{--language-label-base:$base;"
             . "--language-label-base-h:$h;--language-label-base-s:$s%;"
             . "--language-label-base-l:$l%;--language-label-base-fg-l:$fgLightness%}";
    }

    /**
     * @param string $hex #rgb or #rrggbb
     * @return array array(hue 0-359, saturation %, lightness %)
     */
    protected function _hexToHsl($hex)
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $h = $s = 0;

        if ($max !== $min) {
            $d = $max - $min;
            $s = $d / (1 - abs(2 * $l - 1));
            if ($max === $r) {
                $h = 60 * fmod(($g - $b) / $d + 6, 6);
            } elseif ($max === $g) {
                $h = 60 * (($b - $r) / $d + 2);
            } else {
                $h = 60 * (($r - $g) / $d + 4);
            }
        }

        return array((int) round($h), (int) round($s * 100), (int) round($l * 100));
    }

    /**
     * @return Element|false
     */
    protected function _getElement()
    {
        if ($this->_element === null) {
            $id = (int) get_option('language_labels_element_id');
            $element = $id ? $this->_db->getTable('Element')->find($id) : null;
            $this->_element = $element ? $element : false;
        }
        return $this->_element;
    }

    protected function _findDefaultElementId()
    {
        $element = $this->_db->getTable('Element')
            ->findByElementSetNameAndElementName('Dublin Core', 'Language');
        return $element ? (int) $element->id : 0;
    }

    /**
     * Parse "value = label" lines into key => label.
     *
     * @return array
     */
    protected function _getCustomMap()
    {
        if ($this->_customMap !== null) {
            return $this->_customMap;
        }

        $this->_customMap = array();
        $index = $this->_getIndex();
        $lines = preg_split('/\r\n|\r|\n/', (string) get_option('language_labels_custom_map'));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            list($from, $to) = explode('=', $line, 2);
            $from = $this->_lower(trim($from));
            if (isset($index[$from])) {
                $from = $index[$from]['code'];
            }
            if ($from !== '') {
                $this->_customMap[$from] = trim($to);
            }
        }
        return $this->_customMap;
    }

    /**
     * Lower-cased alias (codes, English and translated names) => code and name.
     *
     * @return array
     */
    protected function _getIndex()
    {
        if ($this->_index !== null) {
            return $this->_index;
        }

        $this->_index = array();
        foreach ($this->_getLanguages() as $row) {
            list($one, $bib, $term, $english) = $row;
            $info = array(
                'code' => $one !== '' ? $one : $bib,
                'name' => __($english),
            );
            foreach (array($one, $bib, $term, $english, $info['name']) as $alias) {
                if ($alias !== '') {
                    $this->_index[$this->_lower($alias)] = $info;
                }
            }
        }
        return $this->_index;
    }

    /**
     * Built-in languages: ISO 639-1, 639-2/B, 639-2/T (if different), name.
     * Not exhaustive; add others with custom labels.
     *
     * @return array
     */
    protected function _getLanguages()
    {
        return array(
            array('am', 'amh', '',    'Amharic'),
            array('ar', 'ara', '',    'Arabic'),
            array('hy', 'arm', 'hye', 'Armenian'),
            array('eu', 'baq', 'eus', 'Basque'),
            array('bn', 'ben', '',    'Bengali'),
            array('bg', 'bul', '',    'Bulgarian'),
            array('ca', 'cat', '',    'Catalan'),
            array('zh', 'chi', 'zho', 'Chinese'),
            array('hr', 'hrv', '',    'Croatian'),
            array('cs', 'cze', 'ces', 'Czech'),
            array('da', 'dan', '',    'Danish'),
            array('nl', 'dut', 'nld', 'Dutch'),
            array('en', 'eng', '',    'English'),
            array('eo', 'epo', '',    'Esperanto'),
            array('et', 'est', '',    'Estonian'),
            array('fi', 'fin', '',    'Finnish'),
            array('fr', 'fre', 'fra', 'French'),
            array('ka', 'geo', 'kat', 'Georgian'),
            array('de', 'ger', 'deu', 'German'),
            array('el', 'gre', 'ell', 'Greek'),
            array('',   'grc', '',    'Ancient Greek'),
            array('he', 'heb', '',    'Hebrew'),
            array('hi', 'hin', '',    'Hindi'),
            array('hu', 'hun', '',    'Hungarian'),
            array('is', 'ice', 'isl', 'Icelandic'),
            array('id', 'ind', '',    'Indonesian'),
            array('ga', 'gle', '',    'Irish'),
            array('it', 'ita', '',    'Italian'),
            array('ja', 'jpn', '',    'Japanese'),
            array('ko', 'kor', '',    'Korean'),
            array('',   'lad', '',    'Ladino'),
            array('la', 'lat', '',    'Latin'),
            array('lv', 'lav', '',    'Latvian'),
            array('lt', 'lit', '',    'Lithuanian'),
            array('ms', 'may', 'msa', 'Malay'),
            array('no', 'nor', '',    'Norwegian'),
            array('fa', 'per', 'fas', 'Persian'),
            array('pl', 'pol', '',    'Polish'),
            array('pt', 'por', '',    'Portuguese'),
            array('ro', 'rum', 'ron', 'Romanian'),
            array('ru', 'rus', '',    'Russian'),
            array('sa', 'san', '',    'Sanskrit'),
            array('gd', 'gla', '',    'Scottish Gaelic'),
            array('sr', 'srp', '',    'Serbian'),
            array('sk', 'slo', 'slk', 'Slovak'),
            array('sl', 'slv', '',    'Slovenian'),
            array('es', 'spa', '',    'Spanish'),
            array('sw', 'swa', '',    'Swahili'),
            array('sv', 'swe', '',    'Swedish'),
            array('ta', 'tam', '',    'Tamil'),
            array('th', 'tha', '',    'Thai'),
            array('tr', 'tur', '',    'Turkish'),
            array('uk', 'ukr', '',    'Ukrainian'),
            array('ur', 'urd', '',    'Urdu'),
            array('vi', 'vie', '',    'Vietnamese'),
            array('cy', 'wel', 'cym', 'Welsh'),
            array('yi', 'yid', '',    'Yiddish'),
            array('',   'mul', '',    'Multiple languages'),
            array('',   'und', '',    'Undetermined'),
            array('',   'zxx', '',    'No linguistic content'),
        );
    }

    protected function _sanitiseOption($key, $value, $default)
    {
        switch ($key) {
            case 'language_labels_element_id':
            case 'language_labels_max':
                return max(0, (int) $value);
            case 'language_labels_position':
                return in_array($value, $this->_positions, true) ? $value : $default;
            case 'language_labels_display':
                return in_array($value, $this->_displays, true) ? $value : $default;
            case 'language_labels_background':
            case 'language_labels_foreground':
                return $this->_hex($value, $default);
            case 'language_labels_base_colour':
                $value = trim($value);
                return preg_match(self::BASE_COLOUR_RE, $value) ? $value : $default;
            case 'language_labels_base_language':
            case 'language_labels_colour_order':
                return trim($value);
            default:
                return $value;
        }
    }

    /** Validated hex colour, or $default. */
    protected function _hex($value, $default)
    {
        $value = trim((string) $value);
        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $value) ? $value : $default;
    }

    protected function _lower($value)
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}

if (!function_exists('language_labels')) {
    /**
     * Theme helper: badge markup for an item, for themes that don't fire
     * public_items_browse_each. Don't combine with the hook on one page.
     *
     * @param Item|null $item Defaults to the current item.
     * @return string
     */
    function language_labels($item = null)
    {
        $plugin = LanguageLabelsPlugin::getInstance();
        if (!$plugin) {
            return '';
        }
        if (!$item) {
            $item = get_current_record('item', false);
        }
        return $plugin->render($item);
    }
}
