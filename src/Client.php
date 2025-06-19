<?php
namespace Plexo\Sdk;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Client implements SecurePaymentGatewayInterface
{
    const VERSION = '2.0';
    const CREDENTIALS_FINGERPRINT     = 1;
    const CREDENTIALS_PEM_FINGERPRINT = 2;
    const CREDENTIALS_PFX_PASSPHRASE  = 3;
    
    private $config;
    private $serverCert;
    private $logger;
    
    private static $env = [
        'test' => 'https://testing.plexo.com.uy:4043/SecurePaymentGateway.svc/',
        'prod' => 'https://pagos.plexo.com.uy:4043/SecurePaymentGateway.svc/',
    ];
    
    /**
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->configureDefaults($options);
        $this->setLogger($this->config['logger']);
        if (!array_key_exists($this->config['env'], self::$env)) {
            $error_message = sprintf("Entorno '%s' no valido. Los entornos disponibles son: 'prod' y 'test'.", $this->config['env']);
            $this->logger->critical($error_message);
            throw new Exception\ConfigurationException($error_message);
        }
        $this->logger->debug('Constructor options', $this->config);
    }
    
    /**
     * @since 0.3.0
     * @param null|LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    // Operations
    
    /**
     *
     * @param (array|\Plexo\Sdk\Models\Authorization) $auth
     * @return array
     * @throws \Exception
     */
    public function Authorize($auth)
    {
        if (is_array($auth)) {
            $auth = Models\Authorization::fromArray($auth);
        }
        if (!($auth instanceof Models\Authorization)) {
            throw new Exception\PlexoException('$auth debe ser del tipo array o \Plexo\Sdk\Models\Authorization');
        }
        return $this->_exec('POST', 'Auth', $auth);
    }
    
    /**
     * @param (array|\Plexo\Sdk\Models\ExpressCheckoutRequest) $expchk
     * @return array
     * @throws \Exception
     */
    public function ExpressCheckout($expchk)
    {
        if (is_array($expchk)) {
            $expchk = Models\ExpressCheckoutRequest::fromArray($expchk);
        }
        if (!($expchk instanceof Models\ExpressCheckoutRequest)) {
            throw new Exception\PlexoException('$$expchk debe ser del tipo array o \Plexo\Sdk\Models\ExpressCheckoutRequest');
        }
        return $this->_exec('POST', 'ExpressCheckout', $expchk);
    }
    
    /**
     *
     * @param (array|\Plexo\Sdk\Models\PaymentRequest) $payment
     * @return \stdClass
     */
    public function Purchase($payment)
    {
        if (is_array($payment)) {
            $payment = Models\PaymentRequest::fromArray($payment);
        }
        if (!($payment instanceof Models\PaymentRequest)) {
            throw new \Exception('$payment debe ser del tipo array o \Plexo\Sdk\Models\PaymentRequest');// FIXME
        }
        return $this->_exec('POST', 'Operation/Purchase', $payment);
    }
    
    /**
     *
     * @param (array|\Plexo\Sdk\Models\ThreeDSRequest) $threeDS
     * @return \stdClass
     */
    public function ThreeDSValidate($threeDS)
    {
        if (is_array($threeDS)) {
            $threeDS = Models\ThreeDSRequest::fromArray($threeDS);
        }
        if (!($threeDS instanceof Models\ThreeDSRequest)) {
            throw new \Exception('$payment debe ser del tipo array o \Plexo\Sdk\Models\ThreeDSRequest');// FIXME
        }
        return $this->_exec('POST', '3DSValidation', $threeDS);
    }
    
    public function Cancel($payment)
    {
        if (is_array($payment)) {
            $payment = Models\CancelRequest::fromArray($payment);
        }
        if (!($payment instanceof Models\CancelRequest)) {
            throw new \Exception('$payment debe ser del tipo array o \Plexo\Sdk\Models\CancelRequest');
        }
        return $this->_exec('POST', 'Operation/Cancel', $payment);
    }
    
    /**
     * @since 0.6.0
     * @return \Plexo\Sdk\Models\Transaction
     */
    public function Refund($payment)
    {
        if (is_array($payment)) {
            $payment = Models\RefundRequest::fromArray($payment);
        }
        if (!($payment instanceof Models\RefundRequest)) {
            throw new \Exception('$payment debe ser del tipo array o \Plexo\Sdk\Models\RefundRequest');
        }
        return new Models\Transaction($this->_exec('POST', 'Operation/Refund', $payment));
    }
    
    /**
     * @param array $payment
     * @return \Plexo\Sdk\Models\Transaction
     */
    public function StartReserve($payment)
    {
        if (is_array($payment)) {
            $payment = Models\ReserveRequest::fromArray($payment);
        }
        return $this->_exec('POST', 'Operation/StartReserve', $payment);
    }
    
    public function EndReserve($reserve)
    {
        if (is_array($reserve)) {
            $reserve = Models\Reserve::fromArray($reserve);
        }
        return $this->_exec('POST', 'Operation/EndReserve', $reserve);
    }
    
    public function Status($payment)
    {
        if (is_array($payment)) {
            $payment = new Message\Reference($payment);
        }
        return $this->_exec('POST', 'Operation/Status', $payment);
    }
    
    // Blacklist
    
    /**
     * @since 0.6.0
     * @return null
     */
    public function BlackListAdd($request)
    {
        if (is_array($request)) {
            $request = Models\BlacklistRequest::fromArray($request);
        }
        return $this->_exec('POST', 'Blacklist/Add', $request);
    }
    
    /**
     * @since 0.6.0
     * @return null
     */
    public function BlackListDelete($request)
    {
        if (is_array($request)) {
            $request = Models\BlacklistRequest::fromArray($request);
        }
        return $this->_exec('POST', 'Blacklist/Delete', $request);
    }
    
    /**
     * @since 0.6.0
     * @return Plexo\Sdk\Models\BlacklistRequest[]
     */
    public function GetBlackList()
    {
        return array_map(fn($item) => Models\BlacklistRequest::fromArray($item), $this->_exec('POST', 'Blacklist'));
    }
    
    // Instruments
    
    public function GetInstruments($info)
    {
        if (is_array($info)) {
            $info = new Models\AuthorizationInfo($info);
        }
        return $this->_exec('POST', 'Instruments', $info);
    }
    
    public function DeleteInstrument($info)
    {
        if (is_array($info)) {
            $info = Models\DeleteInstrumentRequest::fromArray($info);
        }
        return $this->_exec('POST', 'Instruments/Delete', $info);
    }
    
    /**
     * @since 0.3.0
     * @param \Plexo\Sdk\Message\CreateBankInstrumentRequest $request
     * @return \Plexo\Sdk\PaymentInstrument
     */
    public function CreateBankInstrument($request)
    {
        if (is_array($request)) {
            $request = Models\CreateBankInstrumentRequest::fromArray($request);
        }
        return new Models\PaymentInstrument($this->_exec('POST', 'Instruments/Bank', $request));
    }
    
    /**
     * @since 1.4.3
     * @param \Plexo\Sdk\Message\CreateBankInstrumentRequest $request
     * @return \Plexo\Sdk\PaymentInstrument
     */
    public function AddInstrument($request)
    {
        if (is_array($request)) {
            $request = Models\CreateBankInstrumentRequest::fromArray($request);
        }
        return new Models\PaymentInstrument($this->_exec('POST', 'Instruments/Add', $request));
    }
    
    // Issuers
    
    public function GetSupportedIssuers()
    {
        return $this->_exec('POST', 'Issuer');
    }
    
    // Commerces
    
    /**
     * @return array
     */
    public function GetCommerces()
    {
        $commerces = $this->_exec('POST', 'Commerce');
        return array_map(fn($item) => Models\Commerce::fromArray($item), $commerces);
    }
    
    /**
     * @param array $commerce
     * @return \Plexo\Sdk\Models\Commerce
     */
    public function AddCommerce($commerce)
    {
        if (is_array($commerce)) {
            $commerce = Models\Commerce::fromArray($commerce);
            $commerce->CommerceId = null;
        }
        return Models\Commerce::fromArray($this->_exec('POST', 'Commerce/Add', $commerce));
    }
    
    /**
     * @param array $commerce
     * @return \Plexo\Sdk\Models\Commerce
     */
    public function ModifyCommerce($commerce)
    {
        if (is_array($commerce)) {
            $commerce = Models\Commerce::fromArray($commerce);
        }
        return Models\Commerce::fromArray($this->_exec('POST', 'Commerce/Modify', $commerce));
    }
    
    /**
     * @param array $commerce
     * @return void
     */
    public function DeleteCommerce($commerce)
    {
        if (is_array($commerce)) {
            $commerce = Models\Commerce::fromArray($commerce);
            $commerce->Name = null;
        }
        return $this->_exec('POST', 'Commerce/Delete', $commerce);
    }
    
    /**
     * @param array $commerce
     * @return void
     */
    public function SetDefaultCommerce($commerce)
    {
        if (is_array($commerce)) {
            $commerce = Models\Commerce::fromArray($commerce);
            $commerce->Name = null;
        }
        return $this->_exec('POST', 'Commerce/SetDefault', $commerce);
    }
    
    public function GetCommerceIssuers($commerce)
    {
        if (is_array($commerce)) {
            $commerce = Models\Commerce::fromArray($commerce);
            $commerce->Name = null;
        }
        $issuers = $this->_exec('POST', 'Commerce/Issuer', $commerce);
        return array_map(fn($issuer) => new Models\IssuerData($issuer), $issuers);
    }
    
    public function AddIssuerCommerce($commerce)
    {
        if (is_array($commerce)) {
            $commerce = Models\IssuerData::fromArray($commerce);
        }
        return new Models\IssuerData($this->_exec('POST', 'Commerce/Issuer/Add', $commerce));
    }
    
    /**
     * @param array $commerce
     * @return void
     */
    public function DeleteIssuerCommerce($commerce)
    {
        if (is_array($commerce)) {
            $commerce = Models\IssuerData::fromArray($commerce);
            $commerce->Metadata = null;
        }
        return $this->_exec('POST', 'Commerce/Issuer/Delete', $commerce);
    }
    
    /**
     *
     * @param array $commerce
     * @return \Plexo\Sdk\Models\Commerce
     */
    public function GetProvidedCodeCommerce($commerce)
    {
        if (is_array($commerce)) {
            $commerce = Models\Commerce::fromArray($commerce);
            $commerce->CommerceId = null;
        }
        return Models\Commerce::fromArray($this->_exec('POST', 'Commerce/GetProvidedCodeCommerce', $commerce));
    }
    
    // TransactionInfo
    
    /**
     * @since 0.3.0
     * @param (array|\Plexo\Models\TransactionQuery) $query
     * @return \Plexo\Sdk\Models\TransactionCursor
     * @throws Exception\PlexoException
     */
    public function ObtainTransactions($query)
    {
        if (is_array($query)) {
            $query = new Models\TransactionQuery($query);
        }
        if (!($query instanceof Models\TransactionQuery)) {
            throw new Exception\PlexoException('$query debe ser del tipo array o \Plexo\Sdk\Models\TransactionQuery');// FIXME
        }
        return $this->_exec('POST', 'Transactions', $query);
        // return new Models\TransactionCursor($this->_exec('POST', 'Transactions', $query));
    }
    
    /**
     * @since 0.3.0
     * @param (array|\Plexo\Sdk\Models\TransactionQuery) $query
     * @return string
     * @throws Exception\PlexoException
     */
    public function ObtainCSVTransactions($query)
    {
        if (is_array($query)) {
            $query = new Models\TransactionQuery($query);
        }
        if (!($query instanceof Models\TransactionQuery)) {
            throw new Exception\PlexoException('$query debe ser del tipo array o \Plexo\Sdk\Models\TransactionQuery');// FIXME
        }
        return $this->_exec('POST', 'Transactions/CSV', $query);
    }
    
    // Public Key
    
    /**
     *
     * @param string $fingerprint
     * @return array Server response
     */
    public function GetServerPublicKey($fingerprint)
    {
        if (!preg_match('/[0-9a-fA-F]{40}/', $fingerprint)) {
            throw new Exception\PlexoException('El formato de Fingerprint no es válido.');
        }
        $path = sprintf("Key/%s", $fingerprint);
        return $this->_exec('GET', $path);
    }
    
    // VerificationCodes
    
    /**
     * @since 0.3.0
     * @param (array|Plexo\Sdk\Models\CodeRequest) $request
     * @return Plexo\Sdk\Models\Transaction
     * @throws Exception\PlexoException
     */
    public function CodeAction($request)
    {
        if (is_array($request)) {
            $request = Models\CodeRequest::fromArray($request);
        }
        if (!($request instanceof Models\CodeRequest)) {
            throw new Exception\PlexoException('$query debe ser del tipo array o \Plexo\Sdk\Models\CodeRequest');// FIXME
        }
        return new Models\Transaction($this->_exec('POST', 'Code', new IssuerSignedRequest($request)));
    }
    
    private function configureDefaults(array $config)
    {
        $defaults = [
            'env' => 'test',
            'pkey' => 0,
            'logger' => new NullLogger(),
            'base_uri' => null,
        ];
        if ($base_uri = getenv('PLEXO_ENDPOINT')) {
            $defaults['base_uri'] = $base_uri;
        }
        // @deprecated Environment variable PLEXO_ENV is deprecated
        if ($env = getenv('PLEXO_ENV')) {
            $defaults['env'] = $env;
        }
        if ($client = getenv('PLEXO_CREDENTIALS_CLIENT')) {
            $defaults['client'] = $client;
        }
        if ($pem_filename = getenv('PLEXO_CREDENTIALS_PEM_FILENAME')) {
            $defaults['pem_filename'] = $pem_filename;
        }
        if ($pfx_filename = getenv('PLEXO_CREDENTIALS_PFX_FILENAME')) {
            $defaults['pfx_filename'] = $pfx_filename;
        }
        if ($pfx_passphrase = getenv('PLEXO_CREDENTIALS_PFX_PASSPHRASE')) {
            $defaults['pfx_passphrase'] = $pfx_passphrase;
        }
        if ($privkey_fingerprint = getenv('PLEXO_CREDENTIALS_PRIVKEY_FINGERPRINT')) {
            $defaults['privkey_fingerprint'] = $privkey_fingerprint;
        }
        $this->config = $config + $defaults;
        if (!$this->config['base_uri']) {
            if (!array_key_exists($this->config['env'], self::$env)) {
                $error_message = sprintf("Entorno '%s' no válido. Los entornos disponibles son: 'prod' y 'test'.", $this->config['env']);
                $this->logger->critical($error_message);
                throw new Exception\ConfigurationException($error_message);
            }
            $this->config['base_uri'] = self::$env[$this->config['env']];
        }
        $this->config['base_uri'] = trim((string) $this->config['base_uri'], '/') . '/';
        if (isset($this->config['privkey_fingerprint'])) {
            $this->config['pkey'] = isset($this->config['pem_filename']) ? self::CREDENTIALS_FINGERPRINT : self::CREDENTIALS_PEM_FINGERPRINT;
        } elseif(isset($this->config['pfx_filename']) && isset($this->config['pfx_passphrase'])) {
            $this->config['pkey'] = self::CREDENTIALS_PFX_PASSPHRASE;
        }
    }
    
    private function _getClientName()
    {
        if (!array_key_exists('client', $this->config) || empty($this->config['client'])) {
            throw new Exception\ResultCodeException('You must provide a valid client name', ResultCode::ARGUMENT_ERROR);
        }
        $this->logger->info('Using client ', ['client' => $this->config['client']]);
        return $this->config['client'];
    }
    
    private function _getIssuerName()
    {
        if (!array_key_exists('issuer', $this->config) || empty($this->config['issuer'])) {
            throw new Exception\ResultCodeException('You must provide a valid issuer name', ResultCode::ARGUMENT_ERROR);
        }
        $this->logger->info('Using issuer ', ['issuer' => $this->config['issuer']]);
        return $this->config['issuer'];
    }
    
    private function get_user_agent(): string {
        $php_version = phpversion();
        $os = PHP_OS_FAMILY; // Detecta el sistema operativo
        $os_version = php_uname('r'); // Versión del sistema operativo
    
        return sprintf(
            'PHP/%s (%s %s)',
            $php_version,
            $os,
            $os_version
        );
    }

    /**
     *
     * @param string $http_method
     * @param string $path
     * @param Sdk\Message $message
     * @return array Server response
     * @throws Exception\HttpClientException
     * @throws Exception\ResultCodeException
     */
    private function _exec($http_method, $path, $message = null)
    {
        $options = [];
        if ($http_method === 'POST') {
            if ($message instanceof SignedMessage) {
                $signedRequest = $message;
                $message = $signedRequest->getMessage();
            } else {
                $signedRequest = new ClientSignedRequest($message);
            }
            if (!is_null($message)) {
                $errors = $message->validate();
                if (is_array($errors) && count($errors)) {
                    throw new Exception\InvalidArgumentException($errors[0]['error'] . ' in ' . $errors[0]['class']);
                }
            }
            $signedRequest->setClient($this->_getClientName());

            $cert = $this->getCert();
            $signedRequest->sign($cert);

            $options = [
                'headers' => [
                    'Content-Type' => 'application/json; charset=UTF-8',
                ],
                'json' => $signedRequest->toArray(),
            ];
        }
        $this->logger->debug('Sending Request', [$http_method, $path, $options]);

        if ( isset( $options['headers'] ) && is_array( $options['headers'] ) ) {
			$args['headers'] = array_merge( [], $options['headers'] );
        }
        $args = [
            'method'  => strtoupper( $http_method ),
            'headers' => [
                'User-Agent' => sprintf('PlexoSdk/%s %s', self::VERSION, $this->get_user_agent()),
                'Accept'     => 'application/json',
            ],
            'timeout' => 30, 
        ];
        if ( isset( $options['body'] ) ) {
            $args['body'] = $options['body'];
        } elseif ( isset( $options['json'] ) ) {
            $args['body'] = 	json_encode( $options['json'] );
            $args['headers']['Content-Type'] = 'application/json';
        }

        try {
            $baseUri = $this->config['base_uri'] ?: self::$env[$this->config['env']];
			$url = $baseUri . $path;
            $res = $this->custom_remote_request($url, $args);  
			
        } catch (\Exception $exc) {
            throw new Exception\HttpClientException($exc->getMessage(), $exc->getCode(), $exc);
        }
        
        $body = (string) $res['body'];
        $response_obj = json_decode($body, true);

        $certificateStore = Registry::contains('CertificateProvider')
        ? Registry::get('CertificateProvider')
        : Certificate\CertificateProvider::instance();
        if (!($certificateStore instanceof Certificate\CertificateProviderInterface)) {
            throw new Exception\PlexoException(sprintf('%s debe implementar la interfaz Plexo\Sdk\Certificate\CertificateProviderInterface.', $certificateStore::class));
        }
        if (preg_match('/^Key\/([A-Z0-9]{40})$/', $path, $matches)) {
            if ($response_obj['Object']['Object']['Response']['Fingerprint'] === $matches[1]) {
                if ($matches[1] ==! $response_obj['Object']['Object']['Response']['Fingerprint']) {
                    throw new Exception\PlexoException('No fue posible obtener el certificado del servidor.');
                }
                $this->serverCert = Certificate\Certificate::fromServerPublicKey($response_obj['Object']['Object']['Response']['Key'], $matches[1]);
                $certificateStore->save($this->serverCert);
            }
        }

        $signedResponse = new SignedResponse($response_obj['Object']['Object'], $response_obj['Object']['Fingerprint'], $response_obj['Object']['UTCUnixTimeExpiration'], $response_obj['Signature']);
        $fingerprint = $signedResponse->getFingerprint();
        if ((!$this->serverCert || $this->serverCert->fingerprint !== $fingerprint) && !($this->serverCert = $certificateStore->getByFingerprint($fingerprint))) {
            $this->GetServerPublicKey($fingerprint);
        }
        $signedResponse->verify($this->serverCert);

        if ($response_obj['Object']['Object']['ResultCode'] != 0) {
            $errorMessage = $response_obj['Object']['Object']['ErrorMessage'] ?? "No message available";
            throw new Exception\ResultCodeException($errorMessage, $response_obj['Object']['Object']['ResultCode']);
        }
        $response = $signedResponse->getMessage();
        return is_array($response) && array_key_exists('Response', $response) ? $response['Response'] : null;
    }
	function custom_remote_request($url, $args = []) {
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 

		if (isset($args['method'])) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($args['method']));
		}

		if (isset($args['headers']) && is_array($args['headers'])) {
			$headers = [];
			foreach ($args['headers'] as $key => $value) {
				$headers[] = "$key: $value";
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
		}

		if (isset($args['body'])) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body']); 
		}

		$response = curl_exec($ch);

		if (curl_errno($ch)) {
			$error_message = curl_error($ch);
			curl_close($ch);
            throw new \Exception('cURL Error: ' . $error_message);
		}

		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		return [
			'body' => $response,
			'response' => [
				'code' => $http_code,
				'message' => 'OK',
			],
		];
	}    
	
    private function getCert()
    {
        switch ($this->config['pkey']) {
            case self::CREDENTIALS_FINGERPRINT:
                if (!file_exists($this->config['pem_filename']) || !is_readable($this->config['pem_filename'])) {
                    throw new Exception\ConfigurationException(sprintf('Error de configuración. No es posible acceder al archivo pem \'%s\'.', $this->config['pem_filename']));
                }
                $pkey = file_get_contents($this->config['pem_filename']);
                $cert = new Certificate\Certificate(null, $pkey, $this->config['privkey_fingerprint']);
                break;
            case self::CREDENTIALS_PEM_FINGERPRINT:
                if (!Registry::contains('CertificateProvider')) {
                    throw new Exception\ConfigurationException('No se ha registrado la clase \'CertificateProvider\'.');
                }
                $certificateStore = Registry::get('CertificateProvider');
                $cert = $certificateStore->getByFingerprint($this->config['privkey_fingerprint']);
                break;
            case self::CREDENTIALS_PFX_PASSPHRASE:
                if (!file_exists($this->config['pfx_filename']) || !is_readable($this->config['pfx_filename'])) {
                    throw new Exception\ConfigurationException(sprintf('Error de configuración. No es posible acceder al archivo pfx \'%s\'.', $this->config['pfx_filename']));
                }
                $cert = Certificate\Certificate::fromPfxFile($this->config['pfx_filename'], $this->config['pfx_passphrase']);
                break;
            default:
                throw new Exception\ConfigurationException('Error de configuración. Debe especificar su clave privada.');
        }
        return $cert;
    }
}
