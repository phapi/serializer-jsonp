<?php

namespace Phapi\Middleware\Serializer\Jsonp;

use Phapi\Exception\InternalServerError;
use Phapi\Serializer\Serializer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Stream;

/**
 * Class Json
 *
 * Middleware that serializes the response body to JSONP
 *
 * @category Phapi
 * @package  Phapi\Middleware\Serializer\Jsonp
 * @author   Peter Ahinko <peter@ahinko.se>
 * @license  MIT (http://opensource.org/licenses/MIT)
 * @link     https://github.com/phapi/serializer-jsonp
 */
class Jsonp extends Serializer
{

    /**
     * Valid mime types
     *
     * @var array
     */
    protected $mimeTypes = [
        'application/javascript',
        'text/javascript'
    ];

    /**
     * Name of the callback header to be used
     *
     * @var null|string
     */
    private $callbackHeader = null;

    /**
     * The callback function name
     *
     * @var null|string
     */
    private $callback = null;

    /**
     * Regex used to validate the callback function name
     *
     * @var string
     */
    private $regex = '/^[a-zA-Z_$][0-9a-zA-Z_$]*(?:\[(?:"(?:\\\.|[^"\\\])*"|\'(?:\\\.|[^\'\\\])*\'|\d+)\])*?$/';

    /**
     * Reserved words used to validate the callback function name
     *
     * @var array
     */
    private $reserved = [
        'break', 'do', 'instanceof', 'typeof', 'case', 'else', 'new', 'var', 'catch', 'finally', 'return',
        'void', 'continue', 'for', 'switch', 'while', 'debugger', 'function', 'this', 'with', 'default',
        'if', 'throw', 'delete', 'in', 'try', 'class', 'enum', 'extends', 'super', 'const', 'export',
        'import', 'implements', 'let', 'private', 'public', 'yield', 'interface', 'package', 'protected',
        'static', 'null', 'true', 'false',
    ];

    /**
     * Override the parent serializer constructor to allow specifying
     * the name of the header containing the callback function name
     *
     * @param string $callbackHeader
     * @param null $mimeTypes
     */
    public function __construct($callbackHeader = 'X-Callback', $mimeTypes = null)
    {
        $this->callbackHeader = $callbackHeader;

        parent::__construct($mimeTypes);
    }

    /**
     * Override the parent serializer invoke method. The method takes care of the
     * needed functionality to get and validate the callback function name from the
     * specified header as well as handling the http status if an error has occurred.
     * The http status code is included in the body if an error occurs and the http
     * status is changed to 200 since many/all clients will have problems handling
     * a response with a http status that isn't 200.
     *
     * @link http://www.theguardian.com/info/developer-blog/2012/jul/16/http-status-codes-jsonp
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable|null $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next = null)
    {
        $response = $next($request, $response, $next);

        // Get response content type
        $contentType = $this->getContentType($response);

        // Check if the accept header matches this serializers mime types
        if (!in_array($contentType, $this->mimeTypes)) {
            // This serializer does not handle this mime type so there is nothing
            // left to do. Return response.
            return $response;
        }

        // Get callback function name from request header or query string
        if ($request->hasHeader($this->callbackHeader)) {
            $this->callback = $request->getHeaderLine($this->callbackHeader);
        }

        // Validate callback
        $this->callback = ($this->isValidateCallback($this->callback)) ? $this->callback: null;

        // Get status code
        $status = $response->getStatusCode();

        // Check if error
        if ($status !== 200) {

            // Check if the response has a method for getting the unserialized body since
            // it's not part of the default PSR-7 implementation.
            try {
                $unserializedBody = $response->getUnserializedBody();
            } catch (\Exception $e) {
                throw new \RuntimeException('Serializer could not retrieve unserialized body');
            }

            // Check if the body is an array and not empty
            if (is_array($unserializedBody) && !empty($unserializedBody)) {
                // Add HTTP status code to body if not already set
                if (!isset($unserializedBody['HttpStatus'])) {
                    $unserializedBody['HttpStatus'] = $status;
                }

                // Change response status code to 200
                $response = $response->withStatus(200);

                // Set the unserialized body to response
                $response = $response->withUnserializedBody($unserializedBody);

                // Try and encode the array to json
                $json = $this->serialize($unserializedBody);

                // Create a new body with the serialized content
                $body = new Stream('php://memory', 'w+');
                $body->write($json);

                // Add the body to the response
                $response = $response->withBody($body);
            }
        }

        return $response;
    }

    /**
     * Validate a callback function name using regex and a list of
     * reserved words.
     *
     * @param $callback
     * @return bool
     */
    public function isValidateCallback($callback)
    {
        foreach (explode('.', $callback) as $part) {
            if (in_array($part, $this->reserved)) {
                return false;
            }

            if (!preg_match($this->regex, $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Serialize body to json
     *
     * @param array $unserializedBody
     * @return string
     * @throws InternalServerError
     */
    public function serialize(array $unserializedBody = [])
    {
        // Serialize body
        if (false === $json = json_encode($unserializedBody)) {
            // Encode failed, throw error
            throw new InternalServerError('Could not serialize content to JSONP');
        }

        // Return with callback if it was found, else just json string
        return ($this->callback !== null) ? $this->callback . '(' . $json . ')': $json;
    }
}
