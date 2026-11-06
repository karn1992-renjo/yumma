<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Re-encodes an uploaded story video to a phone-friendly MP4 (H.264/AAC,
 * 720p wide at most, ~2.5 Mbps) with the moov atom first ("faststart") so it
 * starts playing before it has finished downloading.
 *
 * Needs the ffmpeg binary on the server. When it isn't installed, or encoding
 * fails, the original upload is returned unchanged -- posting a story must
 * never fail because of optimisation.
 */
class VideoOptimizer
{
    /**
     * @return UploadedFile the optimised copy, or $file itself when nothing was done.
     *                      The caller deletes the copy (see isTemporaryCopy()).
     */
    public static function optimize(UploadedFile $file): UploadedFile
    {
        if (! filter_var(AppSetting::getValue('story_video_optimize', true), FILTER_VALIDATE_BOOLEAN)) {
            return $file;
        }

        $ffmpeg = self::binary();
        if ($ffmpeg === null) {
            return $file;
        }

        $input = $file->getRealPath();
        $output = tempnam(sys_get_temp_dir(), 'story_').'.mp4';

        try {
            $process = new Process([
                $ffmpeg, '-y', '-i', $input,
                '-vf', "scale='min(720,iw)':-2",
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '26',
                '-maxrate', '2500k', '-bufsize', '5000k', '-pix_fmt', 'yuv420p',
                '-c:a', 'aac', '-b:a', '96k',
                '-movflags', '+faststart',
                $output,
            ]);
            $process->setTimeout((int) AppSetting::getValue('story_video_optimize_timeout', 150));
            $process->run();

            if (! $process->isSuccessful() || ! is_file($output) || filesize($output) === 0) {
                Log::warning('Story video optimisation failed; storing the original.', [
                    'exit' => $process->getExitCode(),
                    'error' => substr($process->getErrorOutput(), -400),
                ]);
                @unlink($output);

                return $file;
            }

            // Keep the smaller of the two: a tiny clip can grow when re-encoded.
            if (filesize($output) >= filesize($input) && strtolower($file->getClientOriginalExtension()) === 'mp4') {
                @unlink($output);

                return $file;
            }

            $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.mp4';

            return new UploadedFile($output, $name, 'video/mp4', null, true);
        } catch (\Throwable $e) {
            Log::warning('Story video optimisation crashed; storing the original.', ['error' => $e->getMessage()]);
            @unlink($output);

            return $file;
        }
    }

    public static function isTemporaryCopy(UploadedFile $optimised, UploadedFile $original): bool
    {
        return $optimised->getRealPath() !== $original->getRealPath();
    }

    public static function binary(): ?string
    {
        $configured = trim((string) AppSetting::getValue('ffmpeg_path', ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        return (new ExecutableFinder())->find('ffmpeg');
    }
}
