<?php
/**
 * Класс обработки шаблонов
 * @author Yuri Frantsevich (FYN)
 * Date 26/06/2005
 * @version 4.0.3
 * @copyright 2008-2021
 */

//
// +-----------------------------------------------------------------------+
// | PHP Version 5-7                                                       |
// +-----------------------------------------------------------------------+
// | PHP Template script                                                   |
// | (русская версия)                                                      |
// | Copyright (c) 2005-2021 Yuri Frantsevich                              |
// +-----------------------------------------------------------------------+
// |                                                                       |
// | This library is free software; you can redistribute it and/or         |
// | modify it under the terms of the GNU Lesser General Public            |
// | License as published by the Free Software Foundation; either          |
// | version 2.1 of the License, or (at your option) any later version.    |
// | See http://www.gnu.org/copyleft/lesser.html                           |
// |                                                                       |
// +-----------------------------------------------------------------------+
// | Author: Yuri Frantsevich <frantsevich@gmail.com>                      |
// +-----------------------------------------------------------------------+
//
// $Id: Template.php, v4.0 2021/08/18 09:41:45 amistry Exp $

/**
 * Перед началом работы можно предопределить константы:
 * ROOT_PATH - полный путь к корневой директории
 * TMPL_DIR - путь к директории (папке), относительно корневой директории, в которой расположены директории (папки) стилей (шаблонов)
 * TMPL_STYLE - имя директории (папки) в которой расположены шаблоны по умолчанию
 * TMPL_CACHE - путь к директории, относительно корневой директории, в которой будут сохраняться обработанные шаблоны
 *
 * Все шаблоны верстаются как обыкновенные html страницы
 * В местах, где необходимо вставить значение переменной прописывается код следующего вида:
 * {$имя_переменной}, если необходимо выбрать значение из массива, то пишем - {$имя_массива['ключ_массива']} и т.д.,
 * если ключом массива является переменная - запись принимает вид {$имя_массива[$имя_переменной]}
 *
 * Если из всего шаблона надо выделить только небольшой участок (блок), то этот участок обрамляется
 * тегами комментария следующего вида:
 * <!-- tmplblock: begin -->
 * сам блок
 * <!-- tmplblock: end -->
 *
 * Если в шаблон необходимо вставить php код, то он размешается в теге комментария
 * следующего вида:
 * <!-- tmplphp: $a = 20; for ($i=1; $i <= $a; $i++) { -->
 * {$i}<br>
 * <!-- tmplphp: } -->
 *
 * Если в шаблон необходимо подключить ещё один шаблон, то прописываем следующий код:
 *{tmplinclude: имя_подключаемого_файла}
 *
 * Если включаем необрабатываемый кусок кода то обрамляем его конструкцией вида:
 * ##static_begin##
 * сам код
 * ##static_end##
 * Например: <!-- tmplphp: $a = 1; $b = 2;  echo sum($a, $b); ##static_begin## function sum($a, $b) { return ($a+$b); } ##static_end## -->
 *
 * Вставка PHP-кода внутри тэга - tmpltag="# код #"
 * <input type="checkbox" id="remember" name="admin[remember]" tmpltag="#if ($remember) {#" checked tmpltag="#}#" value="1" class="non">
 *
 */
namespace FYN;

class Templates {
    /**
     * Папка сайта по умолчанию относительно корневой директории
     * @var string
     */
    public $site_path = '';

    /**
     * Папка стилей по умолчанию
     * @var string
     */
    public $path = 'templates';

    /**
     * Стиль по умолчанию
     * @var string
     */
    public $style = 'default';

    /**
     * Папка для временных файлов по умолчанию
     * @var string
     */
    public $cache = 'cache';

    /**
     * Переменные шаблона
     * @var array
     */
    private $_tmplvars = array();

    /**
     * Включение отладчика (шаблоны обрабатываются постоянно)
     * @var bool
     */
    private $debug = true;

    /**
     * Ошибки в результате работы
     * @var array
     */
    private $errors = array();

    /**
     * Абсолютный путь к папке шаблонов
     * @var string
     */
    private $full_path = '';

    /**
     * Абсолютный путь к папке стилей
     * @var string
     */
    private $full_style = '';

    /**
     * Логи
     * @var array
     */
    private $logs = array();

    /**
     * Имя файла в который сохраняется лог
     * @var string
     */
    private $log_file = 'template.log';

    /**
     * Абсолютный путь к кешу (обработанным шаблонам)
     * @var string
     */
    private $full_cache = '';

    /**
     * Сохранять информацию о стиле в сессионную переменную $_SESSION['style']
     *
     * @var bool
     */
    public $use_session = true;

    /**
     * Templates constructor.
     * @param string $path - имя общей папки стилей
     * @param string $style - имя папки стиля
     * @param string $cache - имя папки временных файлов
     */
    public function __construct($path = '', $style = '', $cache = '') {
        if (!defined("SEPARATOR")) {
            $separator = getenv("COMSPEC")? '\\' : '/';
            define("SEPARATOR", $separator);
        }

        if (!defined('SITE_PATH')) define('SITE_PATH', $this->site_path);
        $site_path_for = SEPARATOR.SITE_PATH;

        if (!defined("ROOT_PATH")) {
            if (isset($_SERVER['DOCUMENT_ROOT'])) $root_path = preg_replace("/\/$/", '', $_SERVER['DOCUMENT_ROOT']).$site_path_for;
            else {
                $this_file_path = dirname(__FILE__);
                // !!! Warning !!! Check your folder path!
                $this_folder_path = 'vendor/toropyga/templates/src'; // имя текущей папки
                $this_file_path = str_replace("\\", '/', $this_file_path);
                if ($this_folder_path) {
                    $this_folder_path = str_replace("\\", "/", $this_folder_path);
                    $this_folder_path = preg_replace("/^\//", '', $this_folder_path);
                    $root_path = preg_replace("/\/$this_folder_path/", '', $this_file_path);
                }
                else $root_path = $this_folder_path;
            }
            $repl_separator = getenv("COMSPEC")? '/' : '\\';
            $root_path = str_replace("$repl_separator", SEPARATOR, $root_path);
            /**
             * Полный путь к корневой директории
             *
             */
            define("ROOT_PATH", $root_path);
        }

        if (defined('TMPL_LOG_NAME')) $this->log_file = TMPL_LOG_NAME;
        if (defined("TMPL_DEBUG")) $this->setDebug(TMPL_DEBUG);

        if ($path) $this->setPath($path);
        elseif (defined("TMPL_DIR")) $this->setPath(TMPL_DIR);
        elseif ($this->path) $this->setPath($this->path);

        $session = $this->use_session;
        $this->use_session = false;

        if ($style) $this->setStyle($style);
        elseif (defined("TMPL_STYLE") && TMPL_STYLE) $this->setStyle(TMPL_STYLE);
        elseif ($this->style) $this->setStyle($this->style);
        else $this->setStyle('default');

        $this->use_session = $session;

        if ($cache) $this->setCache($cache);
        elseif (defined("TMPL_CACHE")) $this->setCache(TMPL_CACHE);
        elseif ($this->cache) $this->setCache($this->cache);
    }

    /**
     * Деструктор класса.
     */
    public function __destruct() {
    }

    /**
     * Задание пути к папке шаблонов
     *
     * @param string $path - путь или имя папки шаблонов относительно корневой директории
     * @param bool $no_def - не учитывать предопределённую переменную
     * @return bool
     */
    public function setPath ($path = '', $no_def = false) {
        if ($path || (!$path && $no_def)) $this->path = $path;
        elseif (!$path && defined("TMPL_DIR") && !$no_def) $this->path = TMPL_DIR;
        else {
            $this->setError("setPath", "Path not set");
            return false;
        }
        $repl_separator = getenv("COMSPEC")? '/' : '\\';
        $this->path = trim(strtr($this->path, '\\', '/'));
        $this->path = preg_replace("/^\//", '', $this->path);
        $this->path = preg_replace("/\/$/", '', $this->path);
        $this->path = str_replace("$repl_separator", SEPARATOR, $this->path);
        if ($this->path) $this->full_path = ROOT_PATH.SEPARATOR.$this->path;
        else $this->full_path = ROOT_PATH;
        if (!file_exists($path)) {
            $this->setError("setPath", "Path not exists");
            return false;
        }
        return true;
    }

    /**
     * Задание пути к папке стиля шаблонов
     *
     * @param string $style - путь или имя папки стилей шаблонов относительно папки шаблонов
     * @param bool $no_def - не учитывать предопределённую переменную
     * @return bool
     */
    public function setStyle ($style = '', $no_def = false) {
        if ($style) $this->style = $style;
        elseif (!$style && defined("TMPL_STYLE") && TMPL_STYLE && !$no_def) $this->style = TMPL_STYLE;
        else $this->style = $style;
        if ($this->style && !file_exists($this->full_path.SEPARATOR.$this->style)) {
            $this->setError("setStyle", "Path not exists. $style ".$this->full_path.SEPARATOR.$this->style);
            return false;
        }
        $repl_separator = getenv("COMSPEC")? '/' : '\\';
        $this->style = trim(strtr($this->style, '\\', '/'));
        $this->style = preg_replace("/^\//", '', $this->style);
        $this->style = preg_replace("/\/$/", '', $this->style);
        $this->style = str_replace("$repl_separator", SEPARATOR, $this->style);
        if ($this->style) $this->full_style = $this->full_path.SEPARATOR.$this->style;
        else $this->full_style = $this->full_path;
        if ($this->full_cache) $this->setCache($this->cache);
        if ($this->use_session) $_SESSION['style'] = $this->style;
        return true;
    }

    /**
     * Задание пути к папке обработанных шаблонов
     *
     * @param string $cache - путь или имя папки кеша (обработанных шаблонов) относительно корневой директории
     * @param bool $no_def - не учитывать предопределённую переменную
     * @return bool
     */
    public function setCache ($cache = '', $no_def = false) {
        if ($cache) $this->cache = $cache;
        elseif (!$cache && defined("TMPL_CACHE") && !$no_def) $this->cache = TMPL_CACHE;
        else $this->cache = $cache;
        $repl_separator = getenv("COMSPEC")? '/' : '\\';
        $this->cache = trim(strtr($this->cache, '\\', '/'));
        $this->cache = preg_replace("/^\//", '', $this->cache);
        $this->cache = preg_replace("/\/$/", '', $this->cache);
        $this->cache = str_replace("$repl_separator", SEPARATOR, $this->cache);
        if (!is_dir(ROOT_PATH.SEPARATOR.$this->cache)) {
            if (mkdir(ROOT_PATH.SEPARATOR.$this->cache, 0755)) {
                chmod(ROOT_PATH.SEPARATOR.$this->cache, 0755);
            }
            else {
                $this->setError("setCache", "ERROR cache dir:".ROOT_PATH.SEPARATOR.$this->cache);
                return false;
            }
        }
        if ($this->style) {
            $tmpath = ROOT_PATH.SEPARATOR.$this->cache.SEPARATOR.$this->style;
            if (!is_dir($tmpath)) {
                if (@mkdir($tmpath, 0755)) {
                    @chmod($tmpath, 0755);
                }
                else {
                    $this->setError("setCache", "ERROR cache dir:".$tmpath);
                    return false;
                }
            }
            $this->full_cache = $tmpath;
        }
        elseif ($this->cache) $this->full_cache = ROOT_PATH.SEPARATOR.$this->cache;
        else $this->full_cache = ROOT_PATH;
        return true;
    }

    /**
     * Включение/выключение отладчика
     * @param bool $debug
     */
    public function setDebug ($debug = false) {
        if (is_bool($debug)) $this->debug = $debug;
    }

    /**
     * Собираем все ошибки
     *
     * @param string $function - имя функции в которой произошла ошибка
     * @param string $message - сообщение об ошибке
     */
    private function setError ($function, $message) {
        $this->errors[$function][] = $message;
        if ($this->debug) {
            if (defined("SITE_CHARSET")) $CODE = SITE_CHARSET;
            else $CODE = 'utf-8';
            header("Content-Type: text/html; charset=".$CODE);
            echo "<b>$function:</b> $message";
            exit;
        }
    }

    /**
     * Возвращаем массив ошибок
     * @var bool $to_log - обработать для записи в лог (true) или вернуть как есть (false)
     * @return array
     */
    public function getErrors ($to_log = false) {
        if ($to_log) {
            $this->logs = array();
            if (count($this->errors)) {
                foreach ($this->errors as $name => $rows) {
                    foreach ($rows as $txt) $this->logs[] = "$name: $txt";
                }
            }
            return $this->logs;
        }
        else return $this->errors;
    }

    /**
     * Проверяем наличие обработанного файла и сравниваем время последнего
     * обновления исходного шаблона
     *
     * @param string $filename - имя файла
     * @return mixed
     */
    private function getFile ($filename) {
        $cache = $this->full_cache.SEPARATOR.$filename;
        $templ = $this->full_style.SEPARATOR.$filename;
        if (!file_exists($templ)) {
            $this->setError("getFile", "Template ERROR: file $filename not exists");
            return FALSE;
        }
        if (file_exists($cache)) {
            $cache_info = filemtime($cache);
            $templ_info = filemtime($templ);
            if ($cache_info > $templ_info && !$this->debug) {
                return array('type'=>'include', 'path' => $cache);
            }
        }
        if ($this->getNewCache($filename)) {
            return array('type'=>'include', 'path' => $cache);
        }
        $this->setError("getFile", "Template ERROR: unknow $filename");
        return false;
    }

    /**
     * Создаём новый обработанный файл
     *
     * @param string $filename - имя файла
     * @return boolean
     */
    private function getNewCache ($filename) {
        $cache = $this->full_cache.SEPARATOR.$filename;
        $templ = $this->full_style.SEPARATOR.$filename;
        $file_content = file_get_contents($templ);
        $punct = rand(100, 999);
        $punct = '#K_STYLE_'.$punct.'#';
        $file_content = strtr($file_content, array('.' => $punct));
        if (preg_match("/<!--(\s?)tmplblock:([^.]+)begin(\s?)-->([^.]+)<!--(\s?)tmplblock:([^.]+)end(\s?)-->/", $file_content, $blocks)) {
            $file_content = $blocks[4];
        }
        /**
         * Включение дополительных шаблонов
         */
        if (preg_match_all("/\{(\s?)tmplinclude:([^.]+)\}/U", $file_content, $include)) {
            foreach ($include[2] as $num=>$line) {
                $file_include = trim(strtr($line, array($punct => '.')));
                $search = $include[0][$num];
                $file_content = preg_replace("/$search/", '<?php $this->output("'.$file_include.'"); ?>', $file_content);
            }
        }
        $file_content = strtr($file_content, array($punct => '.'));
        $file_content = $this->getStyle($file_content);
        $file_content = $this->getImage($file_content);
        $file_content = $this->getScript($file_content);
        $file_content = $this->getVars($file_content);
        $file_content = $this->getPHP($file_content);
        $file_content = $this->getTags($file_content);
        if (preg_match_all("/(\<[^\"]+(\\\){1,}(\")+.+\>)/iUs", $file_content, $match)) {
            foreach ($match[0] as $line) {
                $new_line = preg_replace("/(\\\){1,}(\")+/", '"', $line);
                $file_content = strtr($file_content, array($line=>$new_line));
            }
        }
        if ($file = @fopen($cache, 'w')) {
            fwrite($file, $file_content);
            fclose($file);
        }
        return true;
    }

    /**
     * Указываем путь к файлам стилей
     * Файл стилей должен иметь расширение css
     *
     * @param string $file_content - содержимое файла
     * @return string
     */
    public function getStyle ($file_content) {
        $punct = rand(100, 999);
        $punct = '#K_STYLE_'.$punct.'#';
        $file_content = strtr($file_content, array('.' => $punct));
        if ($this->path) {
            if ($this->style) $rel_path = "/".$this->path."/".$this->style;
            else $rel_path = "/".$this->path;
        }
        else $rel_path = "/";
        if (SITE_PATH) $rel_path = '/'.SITE_PATH.$rel_path;
        //if (preg_match_all("/(href\s?=\s?\\\?\"?\'?)((\\\?\/?\w+)+)([\w\#\-\_]+#K_STYLE_\d{3}#css)([^<]+>)/", $file_content, $styles)) {
        if (preg_match_all("/(href\s?=\s?(\"|\'))([^>]+)(#K_STYLE_\d{3}#css)([^<]+>)/U",  $file_content, $styles)) {
            $cn = sizeof($styles)-1;
            foreach($styles[0] as $key=>$stl) {
                $file_content = strtr($file_content, array($stl => $styles[1][$key].$rel_path."/".$styles[3][$key].$styles[4][$key].$styles[$cn][$key]));
            }
        }
        $file_content = strtr($file_content, array($punct => '.'));
        return $file_content;
    }

    /**
     * Указываем пути к изображениям находящимся в атрибутах
     * background и теге img
     * Если путь указан относительно корневой директории сайта, то он не меняется.
     *
     *
     * @param string $file_content - содержимое файла
     * @return string
     */
    private function getImage ($file_content) {
        $punct = rand(100, 999);
        $punct = '#K_STYLE_'.$punct.'#';
        $file_content = strtr($file_content, array('.' => $punct));
        if ($this->path) {
            if ($this->style) $rel_path = "/".$this->path."/".$this->style;
            else $rel_path = "/".$this->path;
        }
        else $rel_path = "/";
        if (SITE_PATH) $rel_path = '/'.SITE_PATH.$rel_path;
        if (preg_match_all("/(background(\s)?=)((\s?\\\?\"?)(\w*\/)?)/i", $file_content, $images)) {
            foreach($images[0] as $key=>$img) {
                $file_content = strtr($file_content, array($img => $images[1][$key].$images[4][$key].$rel_path."/".$images[5][$key]));
            }
            //$file_content = strtr($file_content, array('.' => $punct));
            //$file_content = addslashes(stripslashes($file_content));
        }
        if (preg_match_all("/(background-image:\s*url\()(.+)(\))/iU", $file_content, $images)) {
            foreach($images[0] as $key=>$img) {
                $file_content = strtr($file_content, array($img => $images[1][$key].$rel_path."/".$images[2][$key].$images[3][$key]));
            }
            $file_content = strtr($file_content, array('.' => $punct));
            //$file_content = addslashes(stripslashes($file_content));
        }
        if (preg_match_all("/(<img)([^.]+)(src(\s)?=)\s?([^<]+>)/iU", $file_content, $images)) {
            foreach($images[0] as $key=>$img) {
                if (!preg_match('/^(\\\)?(\"|\')?(\{\$)/', $images[5][$key])) {
                    $begin = preg_replace("/^(\"|\')?([^.]+)/", "\\1", stripslashes($images[5][$key]));
                    $end = preg_replace("/^(\"|\')?([^.]+)/", "\\2", stripslashes($images[5][$key]));
                    //$begin = addslashes($begin);
                    //$end = addslashes($end);
                    $file_content = strtr($file_content, array($img => $images[1][$key].$images[2][$key].$images[3][$key].$begin.$rel_path."/".$end));
                }
            }
            $file_content = strtr($file_content, array('.' => $punct));
            //$file_content = addslashes(stripslashes($file_content));
        }
        $rel_path = strtr($rel_path, array('.' => $punct));
        $file_content = strtr($file_content, array("$rel_path//" => '/'));
        $file_content = strtr($file_content, array($rel_path."/http:" => 'http:'));
        $file_content = strtr($file_content, array($rel_path."/ftp:" => 'ftp:'));
        $file_content = strtr($file_content, array($punct => '.'));
        return $file_content;
    }

    /**
     * Указываем пути к скриптам
     * Если путь указан относительно корневой директории сайта, то он не меняется.
     *
     * @param string $file_content
     * @return string
     */
    private function getScript ($file_content) {
        $punct = rand(100, 999);
        $punct = '#K_STYLE_'.$punct.'#';
        $file_content = strtr($file_content, array('.' => $punct));
        if ($this->path) {
            if ($this->style) $rel_path = "/".$this->path."/".$this->style;
            else $rel_path = "/".$this->path;
        }
        else $rel_path = "/";
        if (SITE_PATH) $rel_path = '/'.SITE_PATH.$rel_path;
        if (preg_match_all("/(<script)([^.]+)(src(\s)?=)\s?([^<]+>)/U", $file_content, $images)) {
            foreach($images[0] as $key=>$img) {
                $begin = preg_replace("/^(\"|\')?([^.]+)/", "\\1", stripslashes($images[5][$key]));
                $end = preg_replace("/^(\"|\')?([^.]+)/", "\\2", stripslashes($images[5][$key]));
                //$begin = addslashes($begin);
                //$end = addslashes($end);
                $file_content = strtr($file_content, array($img => $images[1][$key].$images[2][$key].$images[3][$key].$begin.$rel_path."/".$end));
            }
            $file_content = strtr($file_content, array('.' => $punct));
            //$file_content = addslashes(stripslashes($file_content));
        }
        $rel_path = strtr($rel_path, array('.' => $punct));
        $file_content = strtr($file_content, array("$rel_path//" => '/'));
        $file_content = strtr($file_content, array($rel_path."/http:" => 'http:'));
        $file_content = strtr($file_content, array($rel_path."/https:" => 'https:'));
        $file_content = strtr($file_content, array($rel_path."/ftp:" => 'ftp:'));
        $file_content = strtr($file_content, array($punct => '.'));
        return $file_content;
    }

    /**
     * Обрабатываем включение переменных
     *
     * @param string $file_content
     * @param int $function - замена переменных в функциях PHP (TO DO - разобраться!!!)
     * @return string
     */
    private function getVars ($file_content, $function = 0) {
        $punct = rand(100, 999);
        $vpunct = 'TMPL_VAR_'.$punct."_VAR";
        $punct = '#K_STYLE_'.$punct.'#';
        $file_content = strtr($file_content, array('.' => $punct));
        $file_content = strtr($file_content, array('$' => $vpunct));
        if ($function) $search_line = "/$vpunct(\w+)/";
        else $search_line = "/\{$vpunct([^.]+)\}/U";
        if (preg_match_all("$search_line", $file_content, $vars)) {
            foreach ($vars[0] as $key=>$line) {
                if (preg_match("/^".$vpunct."\d+$/", $line) && $function) continue;
                $var = stripslashes($vars[1][$key]);
                if (preg_match("/^(_SESSION|_REQUEST|_POST|_COOKIE|_GET|_ENV|_SERVER|_FILES)/", $var)) {
                    if (preg_match("/^\{.+\}$/", $line)) $file_content = strtr($file_content, array($line => '<?php echo $'.$var.'; ?>'));
                    continue;
                }
                $my_var = preg_split('/\[/', $var);
                $var = '';
                foreach ($my_var as $num=>$varname) {
                    $varname = strtr($varname, array(']'=>'', '\''=>''));
                    $varname = trim($varname);
                    if (!preg_match("/^\d+$/", $varname)) {
                        if (preg_match("/$vpunct/", $varname)) {
                            $varname = strtr($varname, array($vpunct => ''));
                            $varname = "'$varname'";
                            $varname = '$this->_tmplvars['.$varname.']';
                        }
                        else $varname = "'$varname'";
                    }
                    $var = ($var)?$var."[$varname]":"[$varname]";
                }
                if ($function) $file_content = preg_replace("/$line/", '$this->_tmplvars'.$var, $file_content, 1);
                else {
                    if (!preg_match("/$line/", $file_content)) $file_content = strtr($file_content, array($line => '<?php echo $this->_tmplvars'.$var.'; ?>'));
                    else $file_content = preg_replace("/$line/", '<?php echo $this->_tmplvars'.$var.'; ?>', $file_content, 1);
                }
            }
        }
        $file_content = strtr($file_content, array($vpunct => '$'));
        $file_content = strtr($file_content, array($punct => '.'));
        return $file_content;
    }

    /**
     * Обрабатываем включение PHP кода
     *
     * @param string $file_content
     * @return string
     */

    private function getPHP ($file_content) {
        $punct = rand(100, 999);
        $punct = '#K_STYLE_'.$punct.'#';
        $file_content = strtr($file_content, array('.' => $punct));
        if (preg_match_all("/<!--(\s?)tmplphp:([^.]+)-->/U", $file_content, $phpcode)) {
            foreach ($phpcode[0] as $key=>$line) {
                $strtrstat = array();
                if (preg_match_all("/##static_begin##([^.]+)##static_end##/U", $phpcode[2][$key], $statphp)) {
                    $i = 0;
                    foreach ($statphp[0] as $keys=>$lines) {
                        $i++;
                        $strtrstat['##static_begin####stat_'.$i.'####static_end##'] = $statphp[1][$keys];
                        $phpcode[2][$key] = strtr($phpcode[2][$key], array($statphp[1][$keys]=>'##stat_'.$i.'##'));
                    }
                }
                $new_line = $this->getVars($phpcode[2][$key], 1);
                $new_line = strtr($new_line, $strtrstat);
                //$new_line = addslashes($new_line);
                $file_content = strtr($file_content, array($line => '<?php '.$new_line.' ?>'));
            }
        }
        $file_content = strtr($file_content, array($punct => '.'));
        return $file_content;
    }

    /**
     * Обрабатываем включение PHP кода в тегах
     *
     * @param string $file_content
     * @return string
     */

    private function getTags ($file_content) {
        $punct = rand(100, 999);
        $punct = '#K_STYLE_'.$punct.'#';
        $file_content = strtr($file_content, array('.' => $punct));
        /**
         * TO DO
         * Включить в доки
         */
        if (preg_match_all("/tmpltag=(.+)#([^.]+)#(\\1)/U", $file_content, $phpcode)) {
            foreach ($phpcode[0] as $key=>$line) {
                $new_line = $this->getVars($phpcode[2][$key], 1);
                $file_content = strtr($file_content, array($line => '<?php '.$new_line.' ?>'));
            }
        }
        $file_content = strtr($file_content, array($punct => '.'));
        return $file_content;
    }

    /**
     * Возвращает имена всех используемых в шаблоне переменных
     * без учёта переменных используемых при вставке PHP кода
     *
     * @param string $filename - имя шаблона
     * @return array
     */
    public function getTemplateVarsName ($filename) {
        $templ = $this->full_style."/".$filename;
        $file_content = file_get_contents($templ);
        $punct = rand(100, 999);
        $vpunct = 'TMPL_VAR_'.$punct."&sect;";
        $punct = '#K_STYLE_'.$punct.'#';
        $file_content = strtr($file_content, array('.' => $punct));
        $file_content = strtr($file_content, array('$' => $vpunct));
        $search_line = "\{$vpunct([^.]+)\}";
        $tmpl_vars = array();
        if (preg_match_all("/$search_line/U", $file_content, $vars)) {
            foreach ($vars[0] as $key=>$line) {
                $var = stripslashes($vars[1][$key]);
                $tmpl_vars[] = $var;
            }
        }
        $tmpl_vars = array_values(array_unique($tmpl_vars));
        sort($tmpl_vars);
        return $tmpl_vars;
    }

    /**
     * Задание переменных шаблонов
     *
     * @param array $vars
     * @param integer $merge - объеденить значения
     * @return bool
     */
    public function assign ($vars = array(), $merge = 0) {
        if (!is_array($vars)) return false;
        foreach ($vars as $name=>$value) {
            if ($merge && $this->_tmplvars[$name] && is_array($this->_tmplvars[$name])) {
                $new_array[$name] = $value;
                $new_arrays = array_diff_assoc($new_array[$name], $this->_tmplvars[$name]);
                if (sizeof($new_arrays) > 0) {
                    if (sizeof($this->_tmplvars[$name]) > 0) $this->_tmplvars[$name] = array_merge_recursive($this->_tmplvars[$name], $new_arrays);
                    else $this->_tmplvars[$name] = $new_arrays;
                }
            }
            else $this->_tmplvars[$name] = $value;
        }
        return true;
    }

    /**
     * Вывод заданного шаблона
     *
     * @param string $filename - имя обрабатываемого файла шаблона
     * @param bool $as_string - вернуть как строку (true) или вывести на экран (false)
     * @return mixed
     */
    public function output ($filename, $as_string = false) {
        if (!$filename) return false;
        if ($file = $this->getFile($filename)) {
            if ($file['type'] == 'include') {
                if ($as_string) {
                    ob_start();
                    include $file['path'];
                    $file_content = ob_get_contents();
                    ob_end_clean();
                    return $file_content;
                }
                else {
                    include $file['path'];
                    return true;
                }
            }
            else {
                $this->setError("output", "Class ERROR: Cannot open file $filename");
                return false;
            }
        }
        else {
            $this->setError("output", "Template ERROR: Cannot open file $filename");
            return false;
        }
    }

    /**
     * Возвращаем текущий путь к стилям
     * @return string
     */
    public function returnStylePath () {
        if ($this->path) {
            if ($this->style) $rel_path = "/".$this->path."/".$this->style;
            else $rel_path = "/".$this->path;
        }
        else $rel_path = "/";
        if (SITE_PATH) $rel_path = '/'.SITE_PATH.$rel_path;
        return $rel_path;
    }

    /**
     * Возвращает логи
     * @return array
     */
    public function getLogs () {
        $return['log'] = $this->getErrors(true);
        $return['file'] = $this->log_file;
        return $return;
    }
}