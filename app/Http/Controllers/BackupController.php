<?php

namespace App\Http\Controllers;

use App\Console\Commands\BackupDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * إدارة النسخ الاحتياطية (لمدير النظام فقط)
 */
class BackupController extends Controller
{
    public function index()
    {
        $dir = BackupDatabase::directory();
        $files = File::isDirectory($dir)
            ? collect(File::files($dir))
                ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql.gz'))
                ->sortByDesc(fn ($f) => $f->getMTime())
                ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'time' => \Illuminate\Support\Carbon::createFromTimestamp($f->getMTime())])
                ->values()
            : collect();

        $keep = (int) config('backup.keep', 14);
        $time = config('backup.time', '23:30');

        return view('backups.index', compact('files', 'keep', 'time'));
    }

    public function store()
    {
        @set_time_limit(300);
        $code = Artisan::call('backup:run');
        $output = trim(Artisan::output());

        return back()->with($code === 0 ? 'success' : 'error', $output ?: 'تعذر أخذ النسخة الاحتياطية.');
    }

    public function download(string $file)
    {
        return response()->download($this->path($file));
    }

    public function destroy(string $file)
    {
        File::delete($this->path($file));

        return back()->with('success', 'تم حذف النسخة ' . $file . '.');
    }

    /**
     * اسم الملف فقط، بدون مسارات، وبالصيغة التي ينشئها النظام
     */
    private function path(string $file): string
    {
        abort_unless(preg_match('/^erp-[0-9_\-]+\.sql\.gz$/', $file), 404);
        $path = BackupDatabase::directory() . DIRECTORY_SEPARATOR . $file;
        abort_unless(File::exists($path), 404);

        return $path;
    }
}
