<?php

/**
 * @todo utilize PHPunit
 */
require '../../library/My/LinkFinder.php';

$assertions = array(
    array('http://www.example.com', '<a href="http://www.example.com">http://www.example.com</a>'),
    array('http://www.example.com/', '<a href="http://www.example.com/">http://www.example.com/</a>'),
    array('http://www.example.com.', '<a href="http://www.example.com">http://www.example.com</a>.'),
    array('http://www.example.com,', '<a href="http://www.example.com">http://www.example.com</a>,'),
    array('http://www.example.com ', '<a href="http://www.example.com">http://www.example.com</a> '),

    array(' http://www.example.com', ' <a href="http://www.example.com">http://www.example.com</a>'),
    array(' http://www.example.com ', ' <a href="http://www.example.com">http://www.example.com</a> '),
    array('<a href="http://www.example.com">http://www.example.com</a>', '<a href="http://www.example.com">http://www.example.com</a>'),
    array('http://example.com', '<a href="http://example.com">http://example.com</a>'),
    array('http://example', '<a href="http://example">http://example</a>'),

    array('https://www.example.com', '<a href="https://www.example.com">https://www.example.com</a>'),
    array('ftp://www.example.com', '<a href="ftp://www.example.com">ftp://www.example.com</a>'),
    array('www.example.com', '<a href="http://www.example.com">www.example.com</a>'),
    array('ftp.example.com', '<a href="ftp://ftp.example.com">ftp.example.com</a>'),
    array('http://www.example.com<', '<a href="http://www.example.com">http://www.example.com</a><'),

    array('www.example.com/test', '<a href="http://www.example.com/test">www.example.com/test</a>'),
    array('www.example.com/test/', '<a href="http://www.example.com/test/">www.example.com/test/</a>'),
    array('www.example.com/test/test', '<a href="http://www.example.com/test/test">www.example.com/test/test</a>'),
    array('www.example.com/test,', '<a href="http://www.example.com/test">www.example.com/test</a>,'),
    array('example.com', '<a href="http://example.com">example.com</a>'),

    array('example.com/', '<a href="http://example.com/">example.com/</a>'),
    array('example.com,', '<a href="http://example.com">example.com</a>,'),
    array('example.com/test', '<a href="http://example.com/test">example.com/test</a>'),
    array("http://www.example.com\n", '<a href="http://www.example.com">http://www.example.com</a>'."\n"),
    array("http://www.example.com\ntest", '<a href="http://www.example.com">http://www.example.com</a>'."\ntest"),

    array('shttp://example.com', 'shttp://example.com'),
    array('shttp://www.example.com', 'shttp://www.example.com'),
    array('example.site', 'example.site'),
    array('example.ee', '<a href="http://example.ee">example.ee</a>'),
    array('.com', '.com'),

    array('user@host.net', '<a href="mailto:user@host.net">user@host.net</a>'),
    array('user.name@host.net', '<a href="mailto:user.name@host.net">user.name@host.net</a>'),
    array('user@some.host', '<a href="mailto:user@some.host">user@some.host</a>'),
    array('user@somehost', '<a href="mailto:user@somehost">user@somehost</a>'),
    array('<a href="mailto:user@host.net">user@host.net</a>', '<a href="mailto:user@host.net">user@host.net</a>'),

    array('<a href="mailto:user@host.net">user at host</a>', '<a href="mailto:user@host.net">user at host</a>'),
    array('<a href="mailto:user@host.net?subject=test">user@host.net</a>', '<a href="mailto:user@host.net?subject=test">user@host.net</a>'),
    array('<user@host.net>', '<<a href="mailto:user@host.net">user@host.net</a>>'),
    array('(user@host.net)', '(<a href="mailto:user@host.net">user@host.net</a>)'),
    array('user@host.net.', '<a href="mailto:user@host.net">user@host.net</a>.'),
);

$tests = 0;
$errors = 0;
foreach ($assertions as $assert) {
    $tests++;
    $result = My_LinkFinder::parseText($assert[0]);
    if ($result != $assert[1]) {
        $errors++;
        echo "$tests. #{$assert[0]}#\n   exp: #{$assert[1]}#\n   got: #$result#\n";
    }
}
echo "$tests tests, $errors errors.\n";



