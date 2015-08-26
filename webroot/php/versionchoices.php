<?php

/* mb, 2013-08-19, 2013-08-19 */

class VersionMatcher {

    var $webRootPath = '/home/mbless/public_html';
    var $knownPathBeginnings = array(
        // longest paths first!
        '/typo3cms/drafts/',
        '/typo3cms/extensions/',
        '/typo3cms/',
    );
    var $cont               = true;     // continue?
    var $url                = '';       // 'https://docs.typo3.org/typo3cms/TyposcriptReference/en-us/4.7/Setup/Page/Index.html?id=3#abc'
    var $urlPart1           = '';       // 'https://docs.typo3.org'
    var $urlPart2           = '';       // '/typo3cms/'
    var $urlPart3           = '';       // 'TyposcriptReference/en-us/4.7/Setup/Page/Index.html?id=3#abc'
    var $filePathToUrlPart2 = '';       // '/typo3cms/'  (was once '/TYPO3/')

    var $baseFolder         = '';       // 'TyposcriptReference'
    var $localePath         = '';       // 'en-us'
    var $versionPath        = '';       // '4.7'
    var $relativePath       = '';       // 'Setup/Page'
    var $htmlFile           = '';       // 'Index.html'
    var $query              = '';       //  '?id=3'
    var $fragment           = '';       //  '#abc'

    var $parsedUrl;                     // array

    var $localeKeys         = array();  // the various locales found
    var $resultVersions     = array();  // the result!
    var $htmlResult         = '';
    var $htmlResultIntro    = '
		<table>
			<tr>
				<th>Go to version</th>
			</tr>
	';
    var $htmlResultTrailer  = '
		</table>
	';

    function __construct() {
        // pass
    }

    function isValidVersionFolderName($filename) {
        $isValid = false;
        if (!$isValid) { // named versions
            if (in_array( $filename, array('latest', 'stable'))) {
                $isValid = true;
            }
        }
        if (!$isValid) { // numbered versions (like 1.2.3...)
            $pattern = '~\d+\.\d+(\.\d+)*~is';
            $subpattern = array();
            $result = preg_match($pattern, $filename, $subpattern);
            if ($result and ($subpattern[0] === $filename)) {
                $isValid = true;
            }
        }
        return $isValid;
    }

    function isValidLocaleFolderName($segment) {
        $isValid = false;
        if (!$isValid) { // xx-xx)
            $pattern = '~[a-z][a-z]-[a-z][a-z]~';
            $result = preg_match($pattern, $segment);
            if ($result) {
                $isValid = true;
            }
        }
        return $isValid;
    }

    function parseUrl() {
        $this->parsedUrl = parse_url($this->url);
        $this->urlPart1 = '';
        $this->urlPart1 .= isset($this->parsedUrl['scheme'  ]) ?       $this->parsedUrl['scheme'  ] . '://' : '';
        $this->urlPart1 .= isset($this->parsedUrl['host'    ]) ?       $this->parsedUrl['host'    ]         : '';
        $this->query     = isset($this->parsedUrl['query'   ]) ? '?' . $this->parsedUrl['query'   ]         : '';
        $this->fragment  = isset($this->parsedUrl['fragment']) ? '#' . $this->parsedUrl['fragment']         : '';

        // Step 1: find urlPart2 and urlPart3
        $found = false;
        foreach ($this->knownPathBeginnings as $this->urlPart2) {
            if ($this->startsWith($this->parsedUrl['path'], $this->urlPart2)) {
                $found = true;
                break;
            }
        }
        if ($found) {
            $this->filePathToUrlPart2 = $this->urlPart2;
            $this->urlPart3 = substr($this->parsedUrl['path'], strlen($this->urlPart2));
            $this->urlPart3PathSegments = explode('/', $this->urlPart3);
        } else {
            $this->cont = false;
        }
        # urlPart3PathSegments: array('TyposcriptReference', 'en-us', '4.7', 'Setup', 'Page', 'Index.html');
        if ($this->cont and (count($this->urlPart3PathSegments) < 2)) {
            $this->cont = false;
        }
        $i = 0;
        if ($this->cont) {
            $this->baseFolder = $this->urlPart3PathSegments[$i]; // 'TyposcriptReference'
            $i += 1;
        }
        if ($this->cont) {
            $segment = $this->urlPart3PathSegments[$i];
            if ($this->isValidLocaleFolderName($segment)) {
                $i += 1;
                $this->localePath = $segment;   // 'en-us'
            }
        }
        if ($this->cont) {
            $segment = $this->urlPart3PathSegments[$i];
            if ($this->isValidVersionFolderName($segment)) {
                $this->versionPath = $segment; // '4.7'
                $i += 1;
            }
        }
        if ($this->cont) {
            $this->relativePath = array_slice($this->urlPart3PathSegments, $i); // array('Setup', 'Page','Index.html')
            $this->htmlFile = array_pop($this->relativePath);                   // 'Index.html'
            $this->relativePath = implode('/', $this->relativePath);            // 'Setup/Page'
        }
        return;
    }

    function startsWith($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    function generateOutput() {
        $NL = "\n";
        $result = '';
        $rowCount = 0;

        // 'absPathToHtmlFile' => $absPathToHtmlFile,
        // 'urlPart1'          => $this->urlPart1,
        // 'urlPart2'          => $this->urlPart2,
        // 'baseFolder'        => $this->baseFolder,
        // 'versionFolder'     => $versionFolder,
        // 'relativePath'      => $this->relativePath,
        // 'baseHtmlFile'      => $baseHtmlFile,
        // 'directHtmlFile'    => $directHtmlFile,

        // $this->resultVersions[<version>][<locale>] => Array(
        //    [absPathToHtmlFile] => /home/mbless/public_html/TYPO3/extensions/sphinx/fr-fr/1.1.0/Index.html
        //    [query] => 
        //    [fragment] => 
        //    [urlPart1] => https://docs.typo3.org
        //    [urlPart2] => /typo3cms/extensions/
        //    [baseFolder] => sphinx
        //    [versionFolder] => 1.1.0
        //    [relativePath] => 
        //    [baseHtmlFile] => Index.html
        //    [directHtmlFile] => Index.html
        //)
        
        if ($this->cont and count($this->resultVersions)) {

            $result = $this->htmlResultIntro;

            ksort($this->localeKeys);
            krsort($this->resultVersions);

            if (count($this->localeKeys) > 1) {
                $result .= '<tr>';
                foreach ($this->localeKeys as $localeKey) {
                    $locale = $localeKey === '_' ? 'default' : $localeKey;
                    $result .= '<th>' . htmlspecialchars($locale) . '</th>';
                }
                $result .= '</tr>';
            }

            foreach ($this->resultVersions as $versionName => $localeData) {
                $rowClass = $rowCount % 2 ? ' class="row-odd"' : '';
                $result .= '<tr' . $rowClass . '>';

                // Always loop through locales in the same order
                foreach ($this->localeKeys as $localeKey) {
                    $valueBase = '-';
                    $valueDirect = '-';

                    if (isset($localeData[$localeKey])) {
                        // Current version has corresponding locale
                        $v = $localeData[$localeKey];
                        if (strlen($v['baseHtmlFile'])) {
                            $destUrl = $v['urlPart1'] . $v['urlPart2'] . $v['baseFolder'] . '/';
                            if (strlen($v['localeSegment'])) {
                                $destUrl .= $v['localeSegment'] . '/';
                            }
                            if (strlen($v['versionFolder']) and $v['versionFolder'] !== 'stable') {
                                $destUrl .= $v['versionFolder'] . '/';
                            }
                            if (!(strlen($v['baseHtmlFile'] === 'Index.html' or $v['baseHtmlFile'] === 'index.html'))) {
                                $destUrl .= $v['baseHtmlFile'];
                            }
                            $valueBase = '<a href="' . htmlspecialchars($destUrl) . '">' . htmlspecialchars($versionName) . '</a>';
                        }

                        if (strlen($v['directHtmlFile'])) {
                            $destUrl = $v['urlPart1'] . $v['urlPart2'] . $v['baseFolder'] . '/';
                            if (strlen($v['localeSegment'])) {
                                $destUrl .= $v['localeSegment'] . '/';
                            }
                            if (strlen($v['versionFolder']) and $v['versionFolder'] !== 'stable') {
                                $destUrl .= $v['versionFolder'] . '/';
                            }
                            if (strlen($v['relativePath'])) {
                                $destUrl .= $v['relativePath'] . '/';
                            }
                            if (!(strlen($v['directHtmlFile'] === 'Index.html' or $v['directHtmlFile'] === 'index.html'))) {
                                $destUrl .= $v['directHtmlFile'];
                            }
                            $destUrl .= $v['query'];
                            $destUrl .= $v['fragment'];
                            $valueDirect = '<a href="' . htmlspecialchars($destUrl) . '">' . htmlspecialchars($versionName) . '</a>';
                        }
                    }

                    if ($valueDirect !== '-') {
                        $result .= '<td class="rollover">' . $valueDirect . '</td>';
                    } elseif ($valueBase !== '-') {
                        $result .= '<td class="rollover">' . $valueBase . '</td>';
                    } else {
                        $result .= '<td>' . $valueBase . '</td>';
                    }
                }

                $result .= '</tr>';
                $rowCount += 1;
            }
            $result .= $this->htmlResultTrailer;
        }

        return $result;
    }

    function findVersionsForLocale($absPathToManual, $localeSegment) {
        $directory  = opendir($absPathToManual);
        while (false !== ($filename = readdir($directory))) {
            if (1
                and $filename !== '.'
                and $filename !== '..'
                and is_dir($absPathToManual . '/' . $filename)
                and $this->isValidVersionFolderName($filename)
            ) {
                $versionFolder = $filename; // 'latest', '4.7'
                $baseFound = false;
                $baseHtmlFile = '';
                $directFound = false;
                $directHtmlFile = '';
                foreach (array($this->htmlFile, 'Index.html', 'index.html') as $htmlFile) {
                    if (!strlen($htmlFile)) {
                        $htmlFile = 'Index.html';
                    }
                    if (!$baseFound) {
                        $absPathToHtmlFile = implode('/', array($absPathToManual, $versionFolder, $htmlFile));
                        if (file_exists ($absPathToHtmlFile)) {
                            $baseHtmlFile = $htmlFile; // 'Index.html'
                            $baseFound = true;
                        }
                    }
                    if (!$directFound) {
                        if (strlen($this->relativePath)) {
                            $absPathToHtmlFile = implode('/', array($absPathToManual, $versionFolder, $this->relativePath, $htmlFile));
                        } else {
                            $absPathToHtmlFile = implode('/', array($absPathToManual, $versionFolder, $htmlFile));
                        }
                        if (file_exists ($absPathToHtmlFile)) {
                            // '/home/marble/htdocs/LinuxData200/t3doc/versionswitcher/webroot/typo3cms/TyposcriptReference/latest/Setup/Page/Index.html'
                            $directHtmlFile = $htmlFile;
                            $directFound = true;
                        }
                    }
                    if ($baseFound and $directFound) {
                        break;
                    }
                }
                if ($baseFound) {
                    $key = $versionFolder;
                    $localeKey = $localeSegment ?: '_';
                    if (!in_array($localeKey, $this->localeKeys)) $this->localeKeys[] = $localeKey;
                    $this->resultVersions[$key][$localeKey] = array(
                        'absPathToHtmlFile' => $absPathToHtmlFile,  // '/home/marble/htdocs/LinuxData200/t3doc/versionswitcher/webroot/typo3cms/TyposcriptReference/latest/Setup/Page/Index.html'
                        'query'             => $this->query,        // '?id=3'
                        'fragment'          => $this->fragment,     // '#abc'
                        'urlPart1'          => $this->urlPart1,     // 'https://docs.typo3.org'
                        'urlPart2'          => $this->urlPart2,     // '/typo3cms/'
                        'baseFolder'        => $this->baseFolder,   // 'TyposcriptReference'
                        'localeSegment'     => $localeSegment,      // 'fr-fr'
                        'versionFolder'     => $versionFolder,      // 'latest'
                        'relativePath'      => $this->relativePath, // 'Setup/Page'
                        'baseHtmlFile'      => $baseHtmlFile,       // 'Index.html'
                        'directHtmlFile'    => $directHtmlFile,     // 'Index.html'
                    );
                }
            }
        }
        closedir($directory);
    }

    function findVersions() {
        // $this->webRootPath           '/home/mbless/public_html'
        // $this->filePathToUrlPart2    '/typo3cms/'
        // $this->$baseFolder           'TyposcriptReference'
        // $this->$localePath           'en-us'
        // $this->$versionPath          '4.7'
        // $this->$relativePath         'Setup/Page'
        // $this->$indexFile            'Index.html'
        if (!$this->cont) {
            return;
        }
        $absPathToManual = $this->webRootPath . $this->filePathToUrlPart2 . $this->baseFolder;
        $this->absPathToManual = $absPathToManual; // '/home/marble/htdocs/LinuxData200/t3doc/versionswitcher/webroot/typo3cms/TyposcriptReference'
        $manualStartDirs = array();
        $manualStartDirs[] = array($absPathToManual, '');
        # find locale subfolders
        $pattern = $absPathToManual . '/[a-z][a-z]-[a-z][a-z]';
        foreach (glob($pattern, GLOB_ONLYDIR ) as $absPathToLocalePath) {
            // /home/mbless/public_html/typo3cms/extensions/sphinx/fr-fr
            $pos = strrpos($absPathToLocalePath, '/');
            $localeSegment = substr($absPathToLocalePath, $pos+1);
            $manualStartDirs[] = array($absPathToLocalePath, $localeSegment);
        }
        // Array(
        //     [0] => /home/mbless/public_html/TYPO3/extensions/sphinx
        //     [1] => /home/mbless/public_html/TYPO3/extensions/sphinx/fr-fr
        // )
        foreach ($manualStartDirs as $arr) {
            $manualStartDir = $arr[0];
            $localeSegment  = $arr[1];
            $this->findVersionsForLocale($manualStartDir, $localeSegment);
        }
    }

    function processTheUrl($url, $webRootPath=null) {
        $this->url = $url;
        if (!is_null($webRootPath)) {
            $this->webRootPath = $webRootPath;
        }
        $this->parseUrl();
        $this->findVersions();
        $this->htmlResult = $this->generateOutput();
        return $this->htmlResult;
    }
}

$vm = new VersionMatcher();

$url = $_GET['url'];
$htmlResult = $vm->processTheUrl($url);

echo $htmlResult;

?>