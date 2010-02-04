<?php
/**
 * @package My
 * @subpackage LinkFinder
 * @author Peeter P. Mõtsküla <peeterpaul@motskula.net>
 * @copyright Copyright © 2010 Peeter P. Mõtsküla <peeterpaul@motskula.net>
 * @license http://opensource.org/licenses/bsd-license.html New BSD license
 * @version 0.1.0
 */
/**
 * Class My_LinkFinder contains static functions used to extract URLs
 * from strings
 *
 * @todo phpDoc
 */
class My_LinkFinder
{
    const URL_PRE = '(?<=^|\s|^[({[<;]|\s[({[<;])';
    const URL_MID = '[^\s<>]*?';
    const URL_END = '(?=[.,;!?)\]>}]?(?:\s|$|<))';
    const DOMAINS = '(?:[a-z0-9_.-]+\.(?:com|net|org|info|[a-z]{2}))';

    protected static $_phpMarkdown;

    protected static function _doMarkdown($text)
    {
        if (!isset(self::$_phpMarkdown)) {
            $phpMarkdown = dirname(__FILE__) . '/phpMarkdown/markdown.php';
            if (file_exists($phpMarkdown)) {
                include_once $phpMarkdown;
                self::$_phpMarkdown = TRUE;
            } else {
                self::$_phpMarkdown = FALSE;
            }
        }

        /*
        $text = str_replace('<', '&lt;', $text);
        $text = str_replace('&lt;a href="', '<a href="', $text);
        $text = str_replace('&lt;/a>', '</a>', $text);
        */

        if (self::$_phpMarkdown) {
            $text = Markdown($text);
        } else {
          $text = str_replace("\n", "<br />\n", $text);
        }

        return $text;
    }


    public static function parseText($text)
    {
        $regex =
            self::URL_PRE .
            '((?:(?:https?|ftp)://)|(?:(?:www|ftp)\.))' . // protocol --> $1
            '(' . self::URL_MID . ')' . self::URL_END . // target --> $2
            '';
        $regex = str_replace('/', '\/', $regex);
        $text = preg_replace("/$regex/e",
            "'<a href=\"'.('$1' == 'www.' ? 'http://www.' : ('$1' == 'ftp.' ? 'ftp://ftp.' : ('$1' == '' ? 'http://' : '$1' ))).'$2\">$1$2</a>'", $text);

        $regex =
            self::URL_PRE .
            '(' . self::DOMAINS . '(?:/' . self::URL_MID . ')?)' . self::URL_END . // target --> $1
            '';
        $regex = str_replace('/', '\/', $regex);
        $text = preg_replace("/$regex/", '<a href="http://$1">$1</a>', $text);

        $regex =
            self::URL_PRE .
            '([a-zA-Z0-9._+-]+@[a-z0-9][a-z0-9_.-]*[a-z0-9])' . self::URL_END .
            '';
        $regex = str_replace('/', '\/', $regex);
        $text = preg_replace("/$regex/", '<a href="mailto:$1">$1</a>', $text);

        $text = self::_doMarkdown($text);

        return $text;
    }

}
