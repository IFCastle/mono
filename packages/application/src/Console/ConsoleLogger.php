<?php

declare(strict_types=1);

namespace IfCastle\Application\Console;

use IfCastle\Application\WorkerPool\WorkerTypeEnum;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

final readonly class ConsoleLogger implements ConsoleLoggerInterface
{
    use LoggerTrait;

    public function __construct(private ConsoleOutputInterface $consoleOutput) {}


    #[\Override]
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $verbosity                  = match ($level) {
            LogLevel::DEBUG                                          => ConsoleOutputInterface::VERBOSITY_DEBUG,
            LogLevel::ERROR                                          => ConsoleOutputInterface::VERBOSITY_VERBOSE,
            LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY => ConsoleOutputInterface::VERBOSITY_VERY_VERBOSE,
            default                                                  => ConsoleOutputInterface::VERBOSITY_NORMAL,
        };

        $options                    = $verbosity;

        if ($message instanceof \Throwable) {
            $message                = $message->getMessage();
        } elseif ($message instanceof \Stringable) {
            $message                = $message->__toString();
        }

        if ($context[self::IN_FRAME] ?? false) {
            $message                = $this->formatInFrame($message, $context);
        } else {
            $message                = $this->defaultFormat($message, $context);
        }

        $this->consoleOutput->writeln($message, $options);
    }

    /**
     * @param array<string, scalar> $context
     *
     */
    protected function defaultFormat(string $message, array $context = []): string
    {
        $pid                        = $context[self::PID] ?? \getmypid(); // Get process ID
        $workerType                 = match ($context[self::WORKER] ?? '') {
            WorkerTypeEnum::REACTOR->value  => 'R',
            WorkerTypeEnum::JOB->value      => 'J',
            WorkerTypeEnum::SERVICE->value  => 'S',
            default                         => 'M',
        };

        $operationStatus            = $context[self::STATUS] ?? '';
        $isFailure                  = $context[self::IS_FAILURE] ?? false;
        $noTimestamp                = $context[self::NO_TIMESTAMP] ?? false;

        // Format:
        // 15:30:01 [M] 12345  Main server process started                  [STATUS]
        //                     Continuing with additional details
        //                     that describe the process in more depth.
        // ======================================================================
        // 1. Timestamp - HH:MM:SS, color 1
        // 2. Worker type: M = Main, R = Reactor, J = Job, S = Service - colors
        // 3. PID - color 3
        // 4. Message (with optional status) - white\default
        // 5. Status (optional) - green for success, red for failure

        // Define ANSI colors
        $timestampColor             = "\033[35m"; // Cyan
        $workerColor                = "\033[36m"; // Blue
        $pidColor                   = "\033[33m"; // Yellow
        $statusColor                = $isFailure ? "\033[31m" : "\033[32m"; // Red for failure, green for success
        $resetColor                 = "\033[0m"; // Reset to default

        // Format timestamp
        $timestamp                  = $noTimestamp ? '' : \date('H:i:s');
        $timestampFormatted         = $timestamp !== '' && $timestamp !== '0' ? $timestampColor . $timestamp . $resetColor : '';

        // Format worker type
        $workerTypeFormatted        = $workerColor . "[$workerType]" . $resetColor;

        // Format PID
        $pidFormatted               = $pidColor . \str_pad((string) $pid, 5, ' ', STR_PAD_LEFT) . $resetColor;

        // Format status
        $statusFormatted            = $operationStatus
                                    ? \str_pad($statusColor . "[$operationStatus]" . $resetColor, 10, ' ', STR_PAD_LEFT)
                                    : '';

        // Split message into lines if it exceeds 70 characters
        $maxLineLength              = 70;
        $lines                      = \wordwrap($message, $maxLineLength, "\n", true);
        $messageLines               = \explode("\n", $lines);

        // Format first line with timestamp, worker type, PID, and optional status
        $output                     = \sprintf(
            '%s %s %s %s%s',
            $timestampFormatted,
            $workerTypeFormatted,
            $pidFormatted,
            \str_pad($messageLines[0], $maxLineLength, ' '),
            $statusFormatted
        );

        // Format additional lines with indentation
        $indent                     = \str_repeat(
            ' ', \strlen($timestamp) + \strlen($workerType) + \strlen((string) $pid) + 3 + 2
        );

        foreach (\array_slice($messageLines, 1) as $line) {
            $output                 .= PHP_EOL . $indent . $line;
        }

        return $output;
    }

    /**
     * @param array<string, scalar> $context
     *
     */
    protected function formatInFrame(string $message, array $context = []): string
    {
        $version                    = $context[self::VERSION] ?? '';
        $frameWidth                 = 50;
        $padding                    = 2;
        $innerWidth                 = $frameWidth - 2; // Inner width excluding frame borders

        // Define colors using ANSI escape codes
        $programColor               = "\033[36m"; // Blue for program name
        $versionColor               = "\033[32m"; // Green for a version
        $resetColor                 = "\033[0m";  // Reset color formatting

        // Format message with colors
        $formattedMessage           = $programColor . $message . $resetColor .
                                    ' ' . $versionColor . $version . $resetColor;

        // Calculate message length excluding color codes
        $messageLength              = \mb_strlen((string) \preg_replace('/\033\[[0-9;]*m/', '', $formattedMessage));

        // Center the message in the frame
        $paddedMessage              = \mb_str_pad($formattedMessage, $innerWidth + (\mb_strlen($formattedMessage) - $messageLength) - 4, ' ', STR_PAD_BOTH);

        // Build frame components
        $topBorder                  = '┌' . \str_repeat('─', $innerWidth) . '┐'; // Top border
        $middleLine                 = '│' . \str_pad('', $padding, ' ')
                                          . $paddedMessage . \str_pad('', $padding, ' ')
                                    . '│'; // Middle content
        $bottomBorder               = '└' . \str_repeat('─', $innerWidth) . '┘'; // Bottom border

        // Return the full frame
        return $topBorder . PHP_EOL . $middleLine . PHP_EOL . $bottomBorder . PHP_EOL;
    }
}
