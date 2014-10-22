<?php
namespace Aura\Http\Cookie;

use Aura\Http\Cookie\CookieFactory;
use Aura\Http\Cookie\JarFactory as CookieJarFactory;
use Aura\Http\Message\Factory as MessageFactory;
use Aura\Http\Message\Response\StackBuilder;
use org\bovigo\vfs\vfsStream;

class CookieJarTest extends \PHPUnit_Framework_TestCase
{
    protected $jar_factory;

    protected $cookie_factory;

    protected $jars = [];

    protected $storage;

    protected function setUp()
    {
        parent::setUp();
        $this->cookie_factory = new CookieFactory;
        $this->jar_factory = new CookieJarFactory;

        $this->jars['normal'] = implode(PHP_EOL, [
            "# Netscape HTTP Cookie File",
            "# http://curl.haxx.se/rfc/cookie_spec.html",
            "# This file was generated by Aura. Edit at your own risk!",
            "www.example.com\tFALSE\t/\tFALSE\t1645033667\tfoo\tbar",
            "#HttpOnly_.example.com\tTRUE\t/path\tTRUE\t1645033667\tbar\tfoo",
        ]);

        $this->jars['expired'] = implode(PHP_EOL, [
            "# Netscape HTTP Cookie File",
            "# http://curl.haxx.se/rfc/cookie_spec.html",
            "# This file was generated by Aura. Edit at your own risk!",
            "www.example.com\tFALSE\t/\tFALSE\t1645033667\tfoo\tbar",
            "#HttpOnly_.example.com\tTRUE\t/\tFALSE\t1329677267\tbar2\tfoo",
            "#HttpOnly_.example.com\tTRUE\t/path\tTRUE\t1645033667\tbar\tfoo",
        ]);

        $this->jars['malformed'] = implode(PHP_EOL, [
            "# Netscape HTTP Cookie File",
            "# http://curl.haxx.se/rfc/cookie_spec.html",
            "# This file was generated by Aura. Edit at your own risk!",
            "www.example.com\tFALSE\t/\tFALSE\t1645033667\tfoo\tbar",
            "#HttpOnly_.example.com\tTRUE\t/\tFALSE\t1645033667\tfoo",
            "#HttpOnly_.example.com\tTRUE\t/path\tTRUE\t1645033667\tbar\tfoo",
        ]);

        $this->jars['session'] = implode(PHP_EOL, [
            "# Netscape HTTP Cookie File",
            "# http://curl.haxx.se/rfc/cookie_spec.html",
            "# This file was generated by Aura. Edit at your own risk!",
            "www.example.com\tFALSE\t/\tFALSE\t1645033667\tfoo\tbar",
            "#HttpOnly_.example.com\tFALSE\t/\tTRUE\t0\tbar2\tfoo",
            "#HttpOnly_.example.com\tTRUE\t/path\tTRUE\t1645033667\tbar\tfoo",
        ]);

        $this->jars['empty'] = '';
    }

    protected function newJar($jar_key)
    {
        if ($this->storage) {
            fclose($this->storage);
        }
        $structure = array('resource.txt' => '');
        $root = vfsStream::setup('root', null, $structure);
        $file = vfsStream::url('root/resource.txt');

        $this->storage = fopen($file, 'r+');
        fwrite($this->storage, $this->jars[$jar_key]);
        return $this->jar_factory->newInstance($this->storage);
    }

    public function testLoading()
    {
        $jar = $this->newJar('normal');

        $list   = $jar->getAll();
        $expect = [
            'foowww.example.com/' => $this->cookie_factory->newInstance('foo', [
                    'value'    => 'bar',
                    'expire'   => '1645033667',
                    'path'     => '/',
                    'domain'   => 'www.example.com',
                    'secure'   => false,
                    'httponly' => false,
                ]),
            'bar.example.com/path' => $this->cookie_factory->newInstance('bar', [
                    'value'    => 'foo',
                    'expire'   => '1645033667',
                    'path'     => '/path',
                    'domain'   => '.example.com',
                    'secure'   => true,
                    'httponly' => true,
                ]),
        ];

        $this->assertEquals($expect, $list);
    }

    public function test__constructWithFileName()
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . md5(uniqid());
        $this->assertFalse(file_exists($file));
        $jar = $this->jar_factory->newInstance($file);
        $this->assertTrue(file_exists($file));
        unset($jar);
        $this->assertTrue(file_exists($file));
        unlink($file);
    }

    public function testMalformedLineIsIgnored()
    {
        $jar = $this->newJar('malformed');

        $list   = $jar->getAll();
        $expect = [
            'foowww.example.com/' => $this->cookie_factory->newInstance('foo', [
                    'value'    => 'bar',
                    'expire'   => '1645033667',
                    'path'     => '/',
                    'domain'   => 'www.example.com',
                    'secure'   => false,
                    'httponly' => false,
                ]),
            'bar.example.com/path' => $this->cookie_factory->newInstance('bar', [
                    'value'    => 'foo',
                    'expire'   => '1645033667',
                    'path'     => '/path',
                    'domain'   => '.example.com',
                    'secure'   => true,
                    'httponly' => true,
                ]),
        ];

        $this->assertEquals($expect, $list);
    }

    public function testSaving()
    {
        $jar = $this->newJar('empty');

        $jar->add($this->cookie_factory->newInstance('foo', [
            'value'    => 'bar',
            'expire'   => '1645033667',
            'path'     => '/',
            'domain'   => 'www.example.com',
            'secure'   => false,
            'httponly' => false,
        ]));

        $jar->add($this->cookie_factory->newInstance('bar', [
            'value'    => 'foo',
            'expire'   => '1645033667',
            'path'     => '/path',
            'domain'   => '.example.com',
            'secure'   => true,
            'httponly' => true,
        ]));

        $jar->save();

        $actual = $this->readStorage();
        $expect = $this->jars['normal'];
        $this->assertEquals($expect, $actual);
    }

    protected function readStorage()
    {
        $text = '';
        rewind($this->storage);
        while (! feof($this->storage)) {
            $text .= fread($this->storage, 8192);
        }
        return $text;
    }

    public function testListingAllThatMatch()
    {
        $jar = $this->newJar('normal');

        $list   = $jar->getAll('http://www.example.com/');
        $expect = [
            'foowww.example.com/' => $this->cookie_factory->newInstance('foo', [
                'value'    => 'bar',
                'expire'   => '1645033667',
                'path'     => '/',
                'domain'   => 'www.example.com',
                'secure'   => false,
                'httponly' => false,
            ]),
        ];

        $this->assertEquals($expect, $list);
    }

    public function testListingAllWithoutSchemeOnMatchingUrlException()
    {
        $jar = $this->newJar('normal');
        $this->setExpectedException('Aura\Http\Exception');
        $jar->getAll('www.example.com');
    }

    public function testExpiredCookiesAreNotSaved()
    {
        $jar = $this->newJar('expired');
        $jar->save();

        $actual = $this->readStorage();
        $expect = $this->jars['normal'];
        $this->assertEquals($expect, $actual);
    }

    public function testExpireSessionCookies()
    {
        $jar = $this->newJar('session');
        $jar->expireSessionCookies();

        $list   = $jar->getAll();
        $expect = [
            'foowww.example.com/' => $this->cookie_factory->newInstance('foo', [
                'value'    => 'bar',
                'expire'   => '1645033667',
                'path'     => '/',
                'domain'   => 'www.example.com',
                'secure'   => false,
                'httponly' => false,
            ]),
            'bar.example.com/path' => $this->cookie_factory->newInstance('bar', [
                'value'    => 'foo',
                'expire'   => '1645033667',
                'path'     => '/path',
                'domain'   => '.example.com',
                'secure'   => true,
                'httponly' => true,
            ]),
        ];

        $this->assertEquals($expect, $list);
    }

    public function testAddFromResponseStack()
    {
        $headers = [
            'HTTP/1.1 302 Found',
            'Location: /path',
            'Set-Cookie: foo=bar',
            'Content-Length: 0',
            'Connection: close',
            'Content-Type: text/html',
            'HTTP/1.1 200 OK',
            'Content-Length: 13',
            'Connection: close',
            'Content-Type: text/html',
        ];

        $content = 'Hello World!';

        $builder = new StackBuilder(new MessageFactory);
        $stack = $builder->newInstance($headers, $content, 'http://www.example.com');

        $jar = $this->newJar('empty');
        $jar->addFromResponseStack($stack);

        $actual = $jar->__toString();

        $expect = implode(PHP_EOL, [
            "# Netscape HTTP Cookie File",
            "# http://curl.haxx.se/rfc/cookie_spec.html",
            "# This file was generated by Aura. Edit at your own risk!",
            "www.example.com\tFALSE\t/\tFALSE\t0\tfoo\tbar",
        ]);

        $this->assertSame($expect, $actual);
    }
}
