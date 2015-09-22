<?php

namespace Phapi\Tests\Middleware\Serializer\Jsonp;

use Phapi\Middleware\Serializer\Jsonp\Jsonp;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * @coversDefaultClass \Phapi\Middleware\Serializer\Jsonp
 */
class JsonpTest extends TestCase {

    public function testException()
    {
        $serializer = new Jsonp();
        $this->setExpectedException('\Phapi\Exception\InternalServerError', 'Could not serialize content to JSONP');
        $serializer->serialize([ 'key' => "\xB1\x31"]);
    }

    public function testSerializeNoCallback()
    {
        $serializer = new Jsonp();
        $this->assertEquals('{"key":"value","another key":"second value"}',
            $serializer->serialize(['key' => 'value', 'another key' => 'second value'])
        );
    }

    public function testInvokeWithoutCallbackAndNoError()
    {
        $serializer = new Jsonp();

        $request = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');
        $request->shouldReceive('hasHeader')->with('X-Callback')->andReturn(false);

        $response = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('hasHeader')->with('Content-Type')->andReturn(true);
        $response->shouldReceive('getHeaderLine')->with('Content-Type')->andReturn('application/javascript');
        $response->shouldReceive('getUnserializedBody')->andReturn(['key' => 'value']);
        $response->shouldReceive('withBody')->with(\Mockery::type('Psr\Http\Message\StreamInterface'))->andReturnSelf();

        $next = function ($request, $response, $next) {
            return $response;
        };

        $serializer($request, $response, $next);
    }

    public function testInvokeWithCallbackAndNoError()
    {
        $serializer = new Jsonp();

        $request = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');
        $request->shouldReceive('hasHeader')->with('X-Callback')->andReturn(true);
        $request->shouldReceive('getHeaderLine')->with('X-Callback')->andReturn('someFunction');

        $response = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('hasHeader')->with('Content-Type')->andReturn(true);
        $response->shouldReceive('getHeaderLine')->with('Content-Type')->andReturn('application/javascript');
        $response->shouldReceive('getUnserializedBody')->andReturn(['key' => 'value']);
        $response->shouldReceive('withBody')->with(\Mockery::type('Psr\Http\Message\StreamInterface'))->andReturnSelf();

        $next = function ($request, $response, $next) {
            return $response;
        };

        $serializer($request, $response, $next);
    }

    public function testInvokeWithCallbackAndNoErrorAndSerialize()
    {
        $serializer = new Jsonp();

        $request = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');
        $request->shouldReceive('hasHeader')->with('X-Callback')->andReturn(true);
        $request->shouldReceive('getHeaderLine')->with('X-Callback')->andReturn('someFunction');

        $response = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('hasHeader')->with('Content-Type')->andReturn(true);
        $response->shouldReceive('getHeaderLine')->with('Content-Type')->andReturn('application/javascript');
        $response->shouldReceive('getUnserializedBody')->andReturn(['key' => 'value']);
        $response->shouldReceive('withBody')->with(\Mockery::type('Psr\Http\Message\StreamInterface'))->andReturnSelf();

        $next = function ($request, $response, $next) {
            return $response;
        };

        $serializer($request, $response, $next);

        $expected = 'someFunction({"key":"value","next":"another value"})';
        $array = ['key' => 'value', 'next' => 'another value'];

        $this->assertEquals($expected, $serializer->serialize($array));
    }

    public function dataProviderValidation()
    {
        return [
            [ 'for' , false ],
            [ 'hello' , true ],
            [ 'callback/Function' , false ],
        ];
    }

    /**
     * @dataProvider dataProviderValidation
     */
    public function testValidation($callback, $expected)
    {
        $serializer = new Jsonp();
        $this->assertEquals($expected, $serializer->isValidateCallback($callback));
    }

    public function testInvokeWithCallbackAndErrorAndSerialize()
    {
        $serializer = new Jsonp();

        $request = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');
        $request->shouldReceive('hasHeader')->with('X-Callback')->andReturn(true);
        $request->shouldReceive('getHeaderLine')->with('X-Callback')->andReturn('someFunction');

        $response = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getStatusCode')->andReturn(500);
        $response->shouldReceive('hasHeader')->with('Content-Type')->andReturn(true);
        $response->shouldReceive('getHeaderLine')->with('Content-Type')->andReturn('application/javascript');
        $response->shouldReceive('getUnserializedBody')->andReturn(['error' => 'Internal Server Error']);
        $response->shouldReceive('withBody')->with(\Mockery::type('Psr\Http\Message\StreamInterface'))->andReturnSelf();

        $response->shouldReceive('withStatus')->with(200)->andReturnSelf();
        $response->shouldReceive('withUnserializedBody')->with([ 'error' => 'Internal Server Error', 'HttpStatus' => 500])->andReturnSelf();

        $next = function ($request, $response, $next) {
            return $response;
        };

        $serializer($request, $response, $next);
    }

    public function testNotCompatibleResponseObject()
    {
        $serializer = new Jsonp();

        $request = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');
        $request->shouldReceive('hasHeader')->with('X-Callback')->andReturn(true);
        $request->shouldReceive('getHeaderLine')->with('X-Callback')->andReturn('someFunction');

        $response = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getStatusCode')->andReturn(500);
        $response->shouldReceive('hasHeader')->with('Content-Type')->andReturn(true);
        $response->shouldReceive('getHeaderLine')->with('Content-Type')->andReturn('application/javascript');
        //$response->shouldReceive('getUnserializedBody')->andReturn(['error' => 'Internal Server Error']);
        $response->shouldReceive('withBody')->with(\Mockery::type('Psr\Http\Message\StreamInterface'))->andReturnSelf();

        $response->shouldReceive('withStatus')->with(200)->andReturnSelf();
        $response->shouldReceive('withUnserializedBody')->with([ 'error' => 'Internal Server Error', 'HttpStatus' => 500])->andReturnSelf();

        $next = function ($request, $response, $next) {
            return $response;
        };

        $this->setExpectedException('\RuntimeException', 'Serializer could not retrieve unserialized body');
        $serializer($request, $response, $next);
    }
    
    public function testNoContentType()
    {
        // Serializer
        $serializer = new Jsonp();

        // Container
        $container = \Mockery::mock('Phapi\Contract\Di\Container');
        $container->shouldReceive('offsetGet')->with('acceptTypes')->andReturn([]);
        $container->shouldReceive('offsetSet')->with('acceptTypes', ['application/javascript', 'text/javascript']);
        $serializer->setContainer($container);

        $serializer->registerMimeTypes();

        // Request
        $request = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');

        $response = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('hasHeader')->with('Content-Type')->andReturn(true);
        $response->shouldReceive('getHeaderLine')->with('Content-Type')->andReturn('application/xml');
        //$response->shouldReceive('getUnserializedBody')->andReturn([ 'username' => 'phapi' ]);
        //$response->shouldReceive('withBody')->with(\Mockery::type('Psr\Http\Message\StreamInterface'))->andReturnSelf();

        $serializer($request, $response, function ($request, $response) {
            return $response;
        });
    }
}
