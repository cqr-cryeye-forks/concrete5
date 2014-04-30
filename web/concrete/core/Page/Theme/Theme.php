<?
namespace Concrete\Core\Page\Theme;
use \Concrete\Core\Http\ResponseAssetGroup;
use Loader;
use Page;
use Environment;
use Core;
use \Concrete\Core\Page\Theme\File as PageThemeFile;
use \Concrete\Core\Package\PackageList;
use \Concrete\Core\Foundation\Object;
use PageTemplate;
use Concrete\Core\Page\Theme\GridFramework\GridFramework;
use \Concrete\Core\Page\Single as SinglePage;
use \Concrete\Core\StyleCustomizer\Preset;

/**
*
* A page's theme is a pointer to a directory containing templates, CSS files and optionally PHP includes, images and JavaScript files.
* Themes inherit down the tree when a page is added, but can also be set at the site-wide level (thereby overriding any previous choices.)
* @package Pages and Collections
* @subpackages Themes
*/
class Theme extends Object {

    protected $pThemeName;
    protected $pThemeID;
    protected $pThemeDescription;
    protected $pThemeDirectory;
    protected $pThemeThumbnail;
    protected $pThemeHandle;
    protected $pThemeURL;
    protected $pThemeGridFrameworkHandle = false;

    const E_THEME_INSTALLED = 1;
    const THEME_EXTENSION = ".php";
    const THEME_PRESET_EXTENSION = ".less";
    const FILENAME_TYPOGRAPHY_CSS = "typography.css";
    const FILENAME_EXTENSION_CSS = "css";

    public function registerAssets() {}

    public static function getGlobalList() {
        return static::getList('pkgID > 0');
    }

    public static function getLocalList() {
        return static::getList('pkgID = 0');
    }

    public static function getListByPackage($pkg) {
        return static::getList('pkgID = ' . $pkg->getPackageID());
    }

    public static function getList($where = null) {
        if ($where != null) {
            $where = ' where ' . $where;
        }

        $db = Loader::db();
        $r = $db->query("select pThemeID from PageThemes" . $where);
        $themes = array();
        while ($row = $r->fetchRow()) {
            $pl = static::getByID($row['pThemeID']);
            $themes[] = $pl;
        }
        return $themes;
    }

    public static function getInstalledHandles() {
        $db = Loader::db();
        return $db->GetCol("select pThemeHandle from PageThemes");
    }

    public function supportsGridFramework() {
        return $this->pThemeGridFrameworkHandle != false;
    }

    public function getThemeGridFrameworkObject() {
        if ($this->pThemeGridFrameworkHandle) {
            $pTheme = GridFramework::getByHandle($this->pThemeGridFrameworkHandle);
            return $pTheme;
        }
    }

    public function providesAsset($assetType, $assetHandle) {
        $r = ResponseAssetGroup::get();
        $r->markAssetAsIncluded($assetType, $assetHandle);
    }

    public static function getAvailableThemes($filterInstalled = true) {
        // scans the directory for available themes. For those who don't want to go through
        // the hassle of uploading

        $db = Loader::db();
        $dh = Loader::helper('file');

        $themes = $dh->getDirectoryContents(DIR_FILES_THEMES);
        if ($filterInstalled) {
            // strip out themes we've already installed
            $handles = $db->GetCol("select pThemeHandle from PageThemes");
            $themesTemp = array();
            foreach($themes as $t) {
                if (!in_array($t, $handles)) {
                    $themesTemp[] = $t;
                }
            }
            $themes = $themesTemp;
        }

        if (count($themes) > 0) {
            $themesTemp = array();
            // get theme objects from the file system
            foreach($themes as $t) {
                $th = static::getByFileHandle($t);
                if (!empty($th)) {
                    $themesTemp[] = $th;
                }
            }
            $themes = $themesTemp;
        }
        return $themes;

    }

    public static function getByFileHandle($handle, $dir = DIR_FILES_THEMES) {
        $dirt = $dir . '/' . $handle;
        if (is_dir($dirt)) {
            $res = static::getThemeNameAndDescription($dirt);

            $th = new static;
            $th->pThemeHandle = $handle;
            $th->pThemeDirectory = $dirt;
            $th->pThemeName = $res->pThemeName;
            $th->pThemeDescription = $res->pThemeDescription;
            switch($dir) {
                case DIR_FILES_THEMES:
                    $th->pThemeURL = DIR_REL . '/' . DIRNAME_THEMES . '/' . $handle;
                    break;
            }
            return $th;
        }
    }

    /**
     * Checks the theme for a styles.xml file (which is how customizations happen.)
     * @return boolean
     *
     */
    public function isThemeCustomizable() {
        $env = Environment::get();
        $r = $env->getRecord(DIRNAME_THEMES . '/' . $this->getThemeHandle() . '/' . DIRNAME_CSS . '/' . FILENAME_STYLE_CUSTOMIZER_STYLES, $this->getPackageHandle());
        return $r->exists();
    }

    /**
     * Gets the style list object for this theme.
     * @return \Concrete\Core\StyleCustomizer\StyleList
     */
    public function getThemeCustomizableStyleList() {
        if (!isset($this->styleList)) {
            $env = Environment::get();
            $r = $env->getRecord(DIRNAME_THEMES . '/' . $this->getThemeHandle() . '/' . DIRNAME_CSS . '/' . FILENAME_STYLE_CUSTOMIZER_STYLES, $this->getPackageHandle());
            $this->styleList = \Concrete\Core\StyleCustomizer\StyleList::loadFromXMLFile($r->file);
        }
        return $this->styleList;
    }

    /**
     * Gets the style value list object for this theme.
     * @return \Concrete\Core\StyleCustomizer\Style\ValueList
     */
    public function getThemeCustomizableStyleValueList($preset = false) {
        if (!isset($this->styleValueList)) {
            $env = Environment::get();
            $r = $env->getRecord(DIRNAME_THEMES . '/' . $this->getThemeHandle() . '/' . DIRNAME_CSS . '/' . DIRNAME_STYLE_CUSTOMIZER_PRESETS . '/' . FILENAME_STYLE_CUSTOMIZER_DEFAULT_PRESET_NAME, $this->getPackageHandle());
            $urlroot = $env->getURL(DIRNAME_THEMES . '/' . $this->getThemeHandle() . '/' . DIRNAME_CSS, $this->getPackageHandle());
            $this->styleValueList = \Concrete\Core\StyleCustomizer\Style\ValueList::loadFromLessFile($r->file, $urlroot);
        }
        return $this->styleValueList;
    }

    /**
     * Gets all presets available to this theme.
     */
    public function getThemeCustomizableStylePresets() {
        $presets = array();
        $env = Environment::get();
        if ($this->isThemeCustomizable()) {
            $directory = $env->getPath(DIRNAME_THEMES . '/' . $this->getThemeHandle() . '/' . DIRNAME_CSS . '/' . DIRNAME_STYLE_CUSTOMIZER_PRESETS, $this->getPackageHandle());
            $dh = Loader::helper('file');
            $files = $dh->getDirectoryContents($directory);
            foreach($files as $f) {
                if (strrchr($f, '.') == static::THEME_PRESET_EXTENSION) {
                    $preset = Preset::getFromFile($directory . '/' . $f);
                    if (is_object($preset)) {
                        $presets[] = $preset;
                    }
                }
            }
        }
        usort($presets, function($a, $b) {
            if ($a->isDefaultPreset()) {
                return -1;
            } else {
                return strcasecmp($a->getPresetName(), $b->getPresetName());
            }
        });
        return $presets;
    }
    /**
     * @param string $pThemeHandle
     * @return PageTheme
     */
    public static function getByHandle($pThemeHandle) {
        $where = 'pThemeHandle = ?';
        $args = array($pThemeHandle);
        $pt = static::populateThemeQuery($where, $args);
        return $pt;
    }

    /**
     * @param int $ptID
     * @return PageTheme
     */
    public static function getByID($pThemeID) {
        $where = 'pThemeID = ?';
        $args = array($pThemeID);
        $pt = static::populateThemeQuery($where, $args);
        return $pt;
    }

    protected function populateThemeQuery($where, $args) {
        $db = Loader::db();
        $row = $db->GetRow("select pThemeID, pThemeHandle, pThemeDescription, pkgID, pThemeName, pThemeHasCustomClass from PageThemes where {$where}", $args);
        if ($row['pThemeID']) {
            if ($row['pThemeHasCustomClass']) {
                $class = '\\Concrete\\Theme\\' . Loader::helper('text')->camelcase($row['pThemeHandle'] . '\\PageTheme');
            } else {
                $class = '\\Concrete\Core\\Page\\Theme\\Theme';
            }
            $pl = Core::make($class);
            $pl->setPropertiesFromArray($row);
            $pkgHandle = $pl->getPackageHandle();
            $env = Environment::get();
            $pl->pThemeDirectory = $env->getPath(DIRNAME_THEMES . '/' . $row['pThemeHandle'], $pkgHandle);
            $pl->pThemeURL = $env->getURL(DIRNAME_THEMES . '/' . $row['pThemeHandle'], $pkgHandle);
            return $pl;
        }
    }

    public static function add($pThemeHandle, $pkg = null) {
        if (is_object($pkg)) {
            if (is_dir(DIR_PACKAGES . '/' . $pkg->getPackageHandle())) {
                $dir = DIR_PACKAGES . '/' . $pkg->getPackageHandle() . '/' . DIRNAME_THEMES . '/' . $pThemeHandle;
            } else {
                $dir = DIR_PACKAGES_CORE . '/' . $pkg->getPackageHandle() . '/' . DIRNAME_THEMES . '/' . $pThemeHandle;
            }
            $pkgID = $pkg->getPackageID();
        } else if (is_dir(DIR_FILES_THEMES . '/' . $pThemeHandle)) {
            $dir = DIR_FILES_THEMES . '/' . $pThemeHandle;
            $pkgID = 0;
        } else {
            $dir = DIR_FILES_THEMES_CORE . '/' . $pThemeHandle;
            $pkgID = 0;
        }
        $l = static::install($dir, $pThemeHandle, $pkgID);
        return $l;
    }

    // grabs all files in theme that are PHP based (or html if we go that route) and then
    // lists them out, by type, allowing people to install them as page type, etc...
    public function getFilesInTheme() {

        $dh = Loader::helper('file');
        $templateList = PageTemplate::getList();
        $cts = array();
        foreach($templateList as $pt) {
            $pts[] = $pt->getPageTemplateHandle();
        }

        $filesTmp = $dh->getDirectoryContents($this->pThemeDirectory);
        foreach($filesTmp as $f) {
            if (strrchr($f, '.') == static::THEME_EXTENSION) {
                $fHandle = substr($f, 0, strpos($f, '.'));

                if ($f == FILENAME_THEMES_VIEW) {
                    $type = PageThemeFile::TFTYPE_VIEW;
                } else if ($f == FILENAME_THEMES_DEFAULT) {
                    $type = PageThemeFile::TFTYPE_DEFAULT;
                } else if (in_array($f, SinglePage::getThemeableCorePages())) {
                    $type = PageThemeFile::TFTYPE_SINGLE_PAGE;
                } else if (in_array($fHandle, $pts)) {
                    $type = PageThemeFile::TFTYPE_PAGE_TEMPLATE_EXISTING;
                } else {
                    $type = PageThemeFile::TFTYPE_PAGE_TEMPLATE_NEW;
                }

                $pf = new PageThemeFile();
                $pf->setFilename($f);
                $pf->setType($type);
                $files[] = $pf;
            }
        }

        return $files;
    }

    private static function getThemeNameAndDescription($dir) {
        $res = new \stdClass;
        $res->ptName = '';
        $res->ptDescription = '';
        if (file_exists($dir . '/' . FILENAME_THEMES_DESCRIPTION)) {
            $con = file($dir . '/' . FILENAME_THEMES_DESCRIPTION);
            $res->pThemeName = trim($con[0]);
            $res->pThemeDescription = trim($con[1]);
        }
        return $res;
    }

    public static function exportList($xml) {
        $nxml = $xml->addChild('themes');
        $list = static::getList();
        $pst = static::getSiteTheme();

        foreach($list as $pt) {
            $activated = 0;
            if ($pst->getThemeID() == $pt->getThemeID()) {
                $activated = 1;
            }
            $type = $nxml->addChild('theme');
            $type->addAttribute('handle', $pt->getThemeHandle());
            $type->addAttribute('package', $pt->getPackageHandle());
            $type->addAttribute('activated', $activated);
        }

    }

    protected static function install($dir, $pThemeHandle, $pkgID) {
        if (is_dir($dir)) {
            $db = Loader::db();
            $cnt = $db->getOne("select count(pThemeID) from PageThemes where pThemeHandle = ?", array($pThemeHandle));
            if ($cnt > 0) {
                throw new Exception(static::E_THEME_INSTALLED);
            }
            $res = static::getThemeNameAndDescription($dir);
            $pThemeName = $res->pThemeName;
            $pThemeDescription = $res->pThemeDescription;
            $db->query("insert into PageThemes (pThemeHandle, pThemeName, pThemeDescription, pkgID) values (?, ?, ?, ?)", array($pThemeHandle, $pThemeName, $pThemeDescription, $pkgID));

            $env = Environment::get();
            $env->clearOverrideCache();

            $pt = static::getByID($db->Insert_ID());
            $pt->updateThemeCustomClass();
            return $pt;
        }
    }

    public function updateThemeCustomClass() {
        $env = Environment::get();
        $db = Loader::db();
        $r = $env->getRecord(DIRNAME_THEMES . '/' . '/' . $this->pThemeHandle . '/' . FILENAME_THEMES_CLASS, $this->getPackageHandle());
        if ($r->exists()) {
            $db->Execute("update PageThemes set pThemeHasCustomClass = 1 where pThemeID = ?", array($this->pThemeID));
            $this->pThemeHasCustomClass = true;
        } else {
            $db->Execute("update PageThemes set pThemeHasCustomClass = 0 where pThemeID = ?", array($this->pThemeID));
            $this->pThemeHasCustomClass = false;
        }
    }

    public function getThemeID() {return $this->pThemeID;}
    public function getThemeName() {return $this->pThemeName;}

    /** Returns the display name for this theme (localized and escaped accordingly to $format)
    * @param string $format = 'html'
    *   Escape the result in html format (if $format is 'html').
    *   If $format is 'text' or any other value, the display name won't be escaped.
    * @return string
    */
    public function getThemeDisplayName($format = 'html') {
        $value = $this->getThemeName();
        if(strlen($value)) {
            $value = t($value);
        }
        else {
            $value = t('(No Name)');
        }
        switch($format) {
            case 'html':
                return h($value);
            case 'text':
            default:
                return $value;
        }
    }

    public function getPackageID() {return $this->pkgID;}
    public function getPackageHandle() {
        return \Concrete\Core\Package\PackageList::getHandle($this->pkgID);
    }

    /**
     * Returns whether a theme has a custom class.
     */
    public function hasCustomClass() {return $this->pThemeHasCustomClass;}
    public function getThemeHandle() {return $this->pThemeHandle;}
    public function getThemeDescription() {return $this->pThemeDescription;}
    public function getThemeDisplayDescription($format = 'html') {
        $value = $this->getThemeDescription();
        if(strlen($value)) {
            $value = t($value);
        }
        else {
            $value = t('(No Description)');
        }
        switch($format) {
            case 'html':
                return h($value);
            case 'text':
            default:
                return $value;
        }
    }
    public function getThemeDirectory() {return $this->pThemeDirectory;}
    public function getThemeURL() {return $this->pThemeURL;}
    public function getThemeEditorCSS() {return $this->pThemeURL . '/' . static::FILENAME_TYPOGRAPHY_CSS;}

    public function isUninstallable() {
        return ($this->pThemeDirectory != DIR_FILES_THEMES_CORE . '/' . $this->getThemeHandle());
    }
    public function getThemeThumbnail() {
        if (file_exists($this->pThemeDirectory . '/' . FILENAME_THEMES_THUMBNAIL)) {
            $src = $this->pThemeURL . '/' . FILENAME_THEMES_THUMBNAIL;
        } else {
            $src = ASSETS_URL_THEMES_NO_THUMBNAIL;
        }
        $h = Loader::helper('html');
        $img = $h->image($src, THEMES_THUMBNAIL_WIDTH, THEMES_THUMBNAIL_HEIGHT, array('class' => 'ccm-icon-theme'));
        return $img;
    }

    public function applyToSite() {
        $db = Loader::db();
        $r = $db->query("update CollectionVersions inner join Pages on CollectionVersions.cID = Pages.cID left join Packages on Pages.pkgID = Packages.pkgID set CollectionVersions.pThemeID = ? where cIsTemplate = 0 and (Packages.pkgHandle <> 'core' or pkgHandle is null or Pages.ptID > 0)", array($this->pThemeID));
    }

    public static function getSiteTheme() {
        $c = Page::getByID(HOME_CID);
        return static::getByID($c->getCollectionThemeID());
    }

    public function uninstall() {
        $db = Loader::db();

        $db->query("delete from PageThemes where pThemeID = ?", array($this->pThemeID));
        $env = Environment::get();
        $env->clearOverrideCache();
    }

    public function getThemeGatheringGridItemMargin() {
        return 20;
    }

    public function getThemeGatheringGridItemWidth() {
        return 150;
    }

    public function getThemeGatheringGridItemHeight() {
        return 150;
    }

}
