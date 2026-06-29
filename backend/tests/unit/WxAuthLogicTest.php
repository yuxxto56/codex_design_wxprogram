<?php
declare(strict_types=1);

use app\common\BusinessException;
use app\common\ErrorCode;
use app\logic\WxAuthLogic;

$runner->test('wx login requires appid and secret for real code', function () use ($runner): void {
    $logic = new WxAuthLogic(null, 'test-secret', '', '');

    try {
        $logic->loginByCode('real-code');
        throw new RuntimeException('expected wx login to fail without config');
    } catch (BusinessException $exception) {
        $runner->assertSame(ErrorCode::WX_LOGIN_FAILED, $exception->getCode());
        $runner->assertSame('请配置 WECHAT_APPID 和 WECHAT_SECRET 后再使用微信一键登录', $exception->getMessage());
    }
});

$runner->test('wx login resolves openid through code2session when configured', function () use ($runner): void {
    $calledUrl = '';
    $logic = new WxAuthLogic(null, 'test-secret', 'wx-appid', 'wx-secret', function (string $url) use (&$calledUrl): string {
        $calledUrl = $url;
        return json_encode(['openid' => 'openid_real_user', 'session_key' => 'session']);
    });

    $result = $logic->loginByCode('real-code');

    $runner->assertArrayHasKey('token', $result);
    $runner->assertSame('openid_real_user', $result['user']['openid']);
    $runner->assertTrue(strpos($calledUrl, 'appid=wx-appid') !== false, 'code2session url should include appid');
    $runner->assertTrue(strpos($calledUrl, 'secret=wx-secret') !== false, 'code2session url should include secret');
    $runner->assertTrue(strpos($calledUrl, 'js_code=real-code') !== false, 'code2session url should include login code');
});

$runner->test('wx login explains invalid code from code2session', function () use ($runner): void {
    $logic = new WxAuthLogic(null, 'test-secret', 'wx-appid', 'wx-secret', function (): string {
        return json_encode(['errcode' => 40029, 'errmsg' => 'invalid code, rid: abc']);
    });

    try {
        $logic->loginByCode('bad-code');
        throw new RuntimeException('expected invalid code to fail');
    } catch (BusinessException $exception) {
        $runner->assertSame(ErrorCode::WX_LOGIN_FAILED, $exception->getCode());
        $runner->assertSame('微信登录失败：code 无效或已过期，请重新点击微信登录；如果仍失败，请确认微信开发者工具项目 AppID 与后端 WECHAT_APPID/WECHAT_SECRET 属于同一个小程序', $exception->getMessage());
    }
});

$runner->test('wx login explains invalid appsecret from code2session', function () use ($runner): void {
    $logic = new WxAuthLogic(null, 'test-secret', 'wx-appid', 'wrong-secret', function (): string {
        return json_encode(['errcode' => 40125, 'errmsg' => 'invalid appsecret']);
    });

    try {
        $logic->loginByCode('real-code');
        throw new RuntimeException('expected invalid secret to fail');
    } catch (BusinessException $exception) {
        $runner->assertSame(ErrorCode::WX_LOGIN_FAILED, $exception->getCode());
        $runner->assertSame('微信登录失败：AppSecret 无效，请在微信公众平台复制当前小程序的 AppSecret 到 WECHAT_SECRET', $exception->getMessage());
    }
});
