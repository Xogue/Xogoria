<?php

final class ApiController {
    private ContextManager $contexts;
    private Logger $logger;

    public function __construct(private ServiceFactory $services) {
        $this->contexts = $services->contextManager();
        $this->logger = $services->logger(Logger::CHANNEL_API);
    }

    public function process(): WorkerResult {
        try {
            $input = $this->contexts->getInputData(true);
            $request = $input->getRequest();
            if ($request === '') {
                return WorkerResult::failure('A request type is required.', 'missing_request');
            }

            if ($input->usesExternalIdentity()) {
                $expectedKey = $this->contexts->getPrivateApi()->getWebApiKey();
                $providedKey = $input->getUrlApiKey();
                if ($expectedKey === '' || $providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
                    $this->logger->warning('External API identity failed authentication', ['request' => $request]);
                    return WorkerResult::failure('Invalid API key.', 'unauthorized', 401);
                }
            }

            $this->contexts->refreshIdentity($input);
            $worker = $this->services->createWorker($request);
            if ($worker === null) {
                $this->logger->warning('Unknown API request', ['request' => $request]);
                return WorkerResult::failure('Unknown request type.', 'invalid_request');
            }

            $worker->prime($this->contexts->getWorker(), $input);
            $result = $worker->process();
            $this->logger->info('API request processed', [
                'request' => $request,
                'action' => $input->getAction(),
                'success' => $result->isSuccess(),
            ]);
            return $result;
        } catch (Throwable $error) {
            $this->logger->exception($error);
            return WorkerResult::failure('The request could not be completed.', 'internal_error');
        }
    }

    public function respond(?WorkerResult $result = null): never {
        $result ??= $this->process();
        $status = $result->getHttpStatus();
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo $result->toJson();
        exit;
    }

    public static function error(string $message, int $status = 400): never {
        self::sendJson(['success' => false, 'message' => $message], $status);
    }

    public static function authFailed(string $message = 'Not authorized'): never {
        self::error($message, 403);
    }

    public static function sendJson(array $data, int $status = 200): never {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
