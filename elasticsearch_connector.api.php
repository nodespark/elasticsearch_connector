<?php
use Aws\Credentials\CredentialProvider;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Ring\Future\CompletedFutureArray;
use Psr\Http\Message\ResponseInterface;

/**
 * Modify the connector library options
 *
 * @param array $options
 */
function hook_elasticsearch_connector_load_library_options_alter(array &$options) {
  $psr7Handler = Aws\default_http_handler();
  $signer = new SignatureV4('es', 'eu-west-1');
  $credentialProvider = CredentialProvider::defaultProvider();

  // Construct the handler that will be used by Elasticsearch-PHP
  $options['handler'] = function (array $request) use (
    $psr7Handler,
    $signer,
    $credentialProvider
  ) {
    // Amazon ES listens on standard ports (443 for HTTPS, 80 for HTTP).
    $request['headers']['host'][0]
      = parse_url($request['headers']['host'][0], PHP_URL_HOST);

    // Create a PSR-7 request from the array passed to the handler
    $psr7Request = new Request(
      $request['http_method'],
      (new Uri($request['uri']))
        ->withScheme($request['scheme'])
        ->withHost($request['headers']['host'][0]),
      $request['headers'],
      $request['body']
    );

    // Sign the PSR-7 request with credentials from the environment
    try {
      $signedRequest = $signer->signRequest(
        $psr7Request,
        call_user_func($credentialProvider)->wait()
      );

      // Send the signed request to Amazon ES
      /** @var ResponseInterface $response */
      $response = $psr7Handler(
        $signedRequest,
        [
          'proxy' => 'proxyhost:8080',
        ]
      )->wait();

      // Convert the PSR-7 response to a RingPHP response
      return new CompletedFutureArray(
        [
          'status' => $response->getStatusCode(),
          'headers' => $response->getHeaders(),
          'body' => $response->getBody()->detach(),
          'transfer_stats' => ['total_time' => 0],
          'effective_url' => (string) $psr7Request->getUri(),
        ]
      );
    } catch (GuzzleHttp\Promise\RejectionException $e) {
      /** @var GuzzleHttp\Psr7\Response $response */
      $response = $e->getReason()['response'];
      echo (string) $response->getBody();
      /** @var GuzzleHttp\Psr7\Request $request */
      $request = $e->getReason()['request'];

      var_dump((string) $request->getUri(), $request);
      die;
    }
  };
}
