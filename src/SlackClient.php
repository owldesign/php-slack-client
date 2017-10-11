<?php

namespace Owldesign\SlackClient;

use Owldesign\BasicHttpClient\Request\Message\Body\Body;
use Owldesign\BasicHttpClient\Request\Message\Header\Header;
use Owldesign\BasicHttpClient\Request\Message\Message;
use Owldesign\BasicHttpClient\Request\Request;
use Owldesign\BasicHttpClient\Request\Transport\HttpsTransport;
use Owldesign\CommonException\ApiException\UnexpectedResponseException;
use Owldesign\CommonException\NetworkException\ConnectionTimeoutException;
use Owldesign\CommonException\NetworkException\CurlException;
use Owldesign\CommonException\ParserException\StringifyException;
use Owldesign\UrlUtil\Url;

/**
 * Class SlackClient
 *
 * @package Owldesign\SlackClient
 */
class SlackClient
{

	/**
	 * @var string
	 */
	private $subdomainName;

	/**
	 * @var string
	 */
	private $webhook;

	/**
	 * @var string
	 */
	private $username = 'PHP Slack Client';

	/**
	 * @return string
	 */
	public function getSubdomainName()
	{
		return $this->subdomainName;
	}

	/**
	 * @param string $subdomainName
	 * @return $this
	 */
	public function setSubdomainName($subdomainName)
	{
		if (!is_string($subdomainName)) {
			$argumentType = (is_object($subdomainName)) ? get_class($subdomainName) : gettype($subdomainName);
			throw new \InvalidArgumentException('Expected the subdomain name as string. Got ' . $argumentType);
		}
		$this->subdomainName = $subdomainName;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getWebhook()
	{
		return $this->webhook;
	}

	/**
	 * @param string $webhook
	 * @return $this
	 */
	public function setWebhook($webhook)
	{
		if (!is_string($webhook)) {
			$argumentType = (is_object($webhook)) ? get_class($webhook) : gettype($webhook);
			throw new \InvalidArgumentException('Expected the webhook as string. Got ' . $argumentType);
		}
		$this->webhook = $webhook;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getUsername()
	{
		return $this->username;
	}

	/**
	 * @param string $username
	 * @return $this
	 */
	public function setUsername($username)
	{
		if (!is_string($username)) {
			$argumentType = (is_object($username)) ? get_class($username) : gettype($username);
			throw new \InvalidArgumentException('Expected the username as string. Got ' . $argumentType);
		}
		$this->username = $username;
		return $this;
	}

	/**
	 * @param string $channel
	 * @param SlackMessageInterface $slackMessage
	 * @return $this
	 * @throws StringifyException
	 * @throws UnexpectedResponseException
	 * @throws ConnectionTimeoutException
	 * @throws CurlException
	 */
	public function postToChannel($channel, SlackMessageInterface $slackMessage)
	{
		if (!is_string($channel)) {
			$argumentType = (is_object($channel)) ? get_class($channel) : gettype($channel);
			throw new \InvalidArgumentException('Expected the channel name as string. Got ' . $argumentType);
		}
		if (mb_substr($channel, 0, 1) != '#') {
			throw new \InvalidArgumentException('The channel name is invalid. It has to start with "#".');
		}
		$payload = $this->buildPayload($channel, $slackMessage);
		$endpoint = $this->buildEndpoint();
		$this->performRequest($endpoint, $payload);
		return $this;
	}

	/**
	 * @param string $member
	 * @param SlackMessageInterface $slackMessage
	 * @return $this
	 * @throws StringifyException
	 * @throws UnexpectedResponseException
	 * @throws ConnectionTimeoutException
	 * @throws CurlException
	 */
	public function postToMember($member, SlackMessageInterface $slackMessage)
	{
		if (!is_string($member)) {
			$argumentType = (is_object($member)) ? get_class($member) : gettype($member);
			throw new \InvalidArgumentException('Expected the channel name as string. Got ' . $argumentType);
		}
		if (mb_substr($member, 0, 1) != '@') {
			throw new \InvalidArgumentException('The mamber name is invalid. It has to start with "@".');
		}
		$payload = $this->buildPayload($member, $slackMessage);
		$endpoint = $this->buildEndpoint();
		$this->performRequest($endpoint, $payload);
		return $this;
	}

	/**
	 * @param string $receiver
	 * @param SlackMessageInterface $slackMessage
	 * @return array
	 */
	protected function buildPayload($receiver, SlackMessageInterface $slackMessage)
	{
		$payload = array(
			'channel' => $receiver,
			'username' => $this->getUsername(),
			'text' => $slackMessage->getText(),
			'unfurl_links' => $slackMessage->getUnfurlLinks(),
			'unfurl_media' => $slackMessage->getUnfurlMedia(),
		);
		if (!is_null($slackMessage->getIconUrl())) {
			$payload['icon_url'] = $slackMessage->getIconUrl();
		}
		if ($slackMessage->hasAttachments()) {
			foreach ($slackMessage->getAttachments() as $attachment) {
				if (!isset($payload['attachments'])) {
					$payload['attachments'] = array();
				}
				$payload['attachments'][] = $attachment->toArray();
			}
		}
		return $payload;
	}

	/**
	 * @return string
	 */
	protected function buildEndpoint()
	{
		return $this->getWebhook();
	}

	/**
	 * @param $endpoint
	 * @param array $payload
	 * @throws StringifyException
	 * @throws UnexpectedResponseException
	 * @throws ConnectionTimeoutException
	 * @throws CurlException
	 */
	protected function performRequest($endpoint, array $payload)
	{
		$requestBody = json_encode($payload);
		if ($requestBody === false) {
			throw new StringifyException('Building payload failed.');
		}
		$message = new Message();
		$message
			->addHeader(new Header('Content-Type', array('application/json')))
			->addHeader(new Header('Accept', array('application/json')))
			->setBody(new Body($requestBody));
		$request = new Request();
		$response = $request
			->setTransport(new HttpsTransport())
			->setUrl(new Url($endpoint))
			->setMethod(Request::REQUEST_METHOD_POST)
			->setMessage($message)
			->perform()
			->getResponse();
		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
			throw new UnexpectedResponseException(
				'Slack API responded unexpected with HTTP status "' . (string)$response->getStatusText() . '"'
				. ' and message "' . $response->getBody() . '"'
			);
		}
	}

}