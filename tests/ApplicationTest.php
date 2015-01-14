<?php

namespace ProtonTests;

use Proton;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    public function testSetGet()
    {
        $app = new \Proton\Application();
        $this->assertTrue($app->getContainer() instanceof \League\Container\Container);
        $this->assertTrue($app->getRouter() instanceof \League\Route\RouteCollection);
        $this->assertTrue($app->getEventEmitter() instanceof \League\Event\Emitter);
    }

    public function testArrayAccessContainer()
    {
        $app = new \Proton\Application();
        $app['foo'] = 'bar';

        $this->assertSame('bar', $app['foo']);
        $this->assertTrue(isset($app['foo']));
        unset($app['foo']);
        $this->assertFalse(isset($app['foo']));
    }

    public function testSubscribe()
    {
        $app = new \Proton\Application();

        $app->subscribe('request.received', function ($event) {
            $this->assertTrue($event->getRequest() instanceof \Symfony\Component\HttpFoundation\Request);
        });

        $reflected = new \ReflectionProperty($app, 'eventEmitter');
        $reflected->setAccessible(true);
        $emitter = $reflected->getValue($app);
        $this->assertTrue($emitter->hasListeners('request.received'));

        $foo = null;
        $app->subscribe('response.before', function () use (&$foo) {
            $foo = 'bar';
        });

        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        $response = $app->handle($request);

        $this->assertEquals('bar', $foo);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testTerminate()
    {
        $app = new \Proton\Application();

        $app->subscribe('response.after', function ($event) {
            $this->assertTrue($event->getRequest() instanceof \Symfony\Component\HttpFoundation\Request);
        });

        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        $response = $app->handle($request);

        $app->terminate($request, $response);
    }

    public function testHandleSuccess()
    {
        $app = new \Proton\Application();

        $app->get('/', function ($request, $response) {
            $response->setContent('<h1>It works!</h1>');
            return $response;
        });

        $app->post('/', function ($request, $response) {
            $response->setContent('<h1>It works!</h1>');
            return $response;
        });

        $app->put('/', function ($request, $response) {
            $response->setContent('<h1>It works!</h1>');
            return $response;
        });

        $app->delete('/', function ($request, $response) {
            $response->setContent('<h1>It works!</h1>');
            return $response;
        });

        $app->patch('/', function ($request, $response) {
            $response->setContent('<h1>It works!</h1>');
            return $response;
        });

        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

        $response = $app->handle($request, 1, true);

        $this->assertEquals('<h1>It works!</h1>', $response->getContent());
    }

    public function testHandleFailThrowException()
    {
        $app = new \Proton\Application();

        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

        try {
            $app->handle($request, 1, false);
        } catch (\Exception $e) {
            $this->assertTrue($e instanceof \League\Route\Http\Exception\NotFoundException);
        }
    }

    public function testHandleWithOtherException()
    {
        $app = new \Proton\Application();
        $app['debug'] = true;

        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

        $app->subscribe('request.received', function ($event) {
            throw new \Exception('A test exception');
        });

        $response = $app->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testCustomExceptionDecorator()
    {
        $app = new \Proton\Application();
        $app['debug'] = true;

        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

        $app->subscribe('request.received', function ($event) {
            throw new \Exception('A test exception');
        });

        $app->setExceptionDecorator(function ($e) {
            $response = new \Symfony\Component\HttpFoundation\Response;
            $response->setStatusCode(500);
            $response->setContent('Fail');
            return $response;
        });

        $response = $app->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Fail', $response->getContent());
    }

    /**
     * @expectedException \LogicException
     */
    public function testExceptionDecoratorDoesntReturnResponseObject()
    {
        $app = new \Proton\Application();
        $app->setExceptionDecorator(function ($e) {
            return true;
        });

        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

        $app->subscribe('request.received', function ($event) {
            throw new \Exception('A test exception');
        });

        $response = $app->handle($request);
    }

    public function testCustomEvents()
    {
        $app = new \Proton\Application();

        $time = null;
        $app->subscribe('custom.event', function ($event, $args) use (&$time) {
            $time = $args;
        });

        $app->getEventEmitter()->emit('custom.event', time());
        $this->assertTrue($time !== null);
    }

    public function testRun()
    {
        $app = new \Proton\Application();

        $app->get('/', function ($request, $response) {
            $response->setContent('<h1>It works!</h1>');
            return $response;
        });

        $app->subscribe('request.received', function ($event) {
            $this->assertTrue($event->getRequest() instanceof \Symfony\Component\HttpFoundation\Request);
        });
        $app->subscribe('response.after', function ($event) {
            $this->assertTrue($event->getResponse() instanceof \Symfony\Component\HttpFoundation\Response);
        });

        ob_start();
        $app->run();
        ob_get_clean();
    }
}
