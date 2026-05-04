<?php

declare(strict_types=1);

class YouTubeApiException extends RuntimeException {}
class QuotaExceededException extends YouTubeApiException {}
class AuthenticationException extends YouTubeApiException {}
class VideoNotFoundException extends YouTubeApiException {}
class CommentsDisabledException extends YouTubeApiException {}
class GrokApiException extends RuntimeException {}
