<?php
/**
 * ImportController.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms of the
 * Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */
declare(strict_types=1);

namespace FireflyIII\Http\Controllers;

use Crypt;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Http\Requests\ImportUploadRequest;
use FireflyIII\Import\ImportProcedureInterface;
use FireflyIII\Import\Setup\SetupInterface;
use FireflyIII\Models\ImportJob;
use FireflyIII\Repositories\ImportJob\ImportJobRepositoryInterface;
use FireflyIII\Repositories\Tag\TagRepositoryInterface;
use FireflyIII\Repositories\User\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Log;
use Response;
use Session;
use SplFileObject;
use Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use View;

/**
 * Class ImportController
 *
 * @package FireflyIII\Http\Controllers
 */
class ImportController extends Controller
{
    /**
     *
     */
    public function __construct()
    {
        parent::__construct();

        $this->middleware(
            function ($request, $next) {
                View::share('mainTitleIcon', 'fa-archive');
                View::share('title', trans('firefly.import_data_full'));

                return $next($request);
            }
        );
    }

    /**
     * This is the last step before the import starts.
     *
     * @param ImportJob $job
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|View
     */
    public function complete(ImportJob $job)
    {
        Log::debug('Now in complete()', ['job' => $job->key]);
        if (!$this->jobInCorrectStep($job, 'complete')) {
            return $this->redirectToCorrectStep($job);
        }
        $subTitle     = trans('firefly.import_complete');
        $subTitleIcon = 'fa-star';

        return view('import.complete', compact('job', 'subTitle', 'subTitleIcon'));
    }

    /**
     * This is step 3.
     * This is the first step in configuring the job. It can only be executed
     * when the job is set to "import_status_never_started".
     *
     * @param ImportJob $job
     *
     * @return View
     * @throws FireflyException
     */
    public function configure(ImportJob $job)
    {
        Log::debug('Now at start of configure()');
        if (!$this->jobInCorrectStep($job, 'configure')) {
            return $this->redirectToCorrectStep($job);
        }

        // actual code
        $importer = $this->makeImporter($job);
        $importer->configure();
        $data         = $importer->getConfigurationData();
        $subTitle     = trans('firefly.configure_import');
        $subTitleIcon = 'fa-wrench';

        return view('import.' . $job->file_type . '.configure', compact('data', 'job', 'subTitle', 'subTitleIcon'));
    }

    /**
     * Generate a JSON file of the job's config and send it to the user.
     *
     * @param ImportJob $job
     *
     * @return mixed
     */
    public function download(ImportJob $job)
    {
        Log::debug('Now in download()', ['job' => $job->key]);
        $config                            = $job->configuration;
        $config['column-roles-complete']   = false;
        $config['column-mapping-complete'] = false;
        $config['delimiter']               = $config['delimiter'] === "\t" ? 'tab' : $config['delimiter'];
        $result                            = json_encode($config, JSON_PRETTY_PRINT);
        $name                              = sprintf('"%s"', addcslashes('import-configuration-' . date('Y-m-d') . '.json', '"\\'));

        /** @var LaravelResponse $response */
        $response = response($result, 200);
        $response->header('Content-disposition', 'attachment; filename=' . $name)
                 ->header('Content-Type', 'application/json')
                 ->header('Content-Description', 'File Transfer')
                 ->header('Connection', 'Keep-Alive')
                 ->header('Expires', '0')
                 ->header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                 ->header('Pragma', 'public')
                 ->header('Content-Length', strlen($result));

        return $response;


    }

    /**
     * @param ImportJob $job
     *
     * @return View
     */
    public function finished(ImportJob $job)
    {
        if (!$this->jobInCorrectStep($job, 'finished')) {
            return $this->redirectToCorrectStep($job);
        }

        // if there is a tag (there might not be), we can link to it:
        $tagId = $job->extended_status['importTag'] ?? 0;

        $subTitle     = trans('firefly.import_finished');
        $subTitleIcon = 'fa-star';

        return view('import.finished', compact('job', 'subTitle', 'subTitleIcon', 'tagId'));
    }

    /**
     * This is step 1. Upload a file.
     *
     * @return View
     */
    public function index()
    {
        Log::debug('Now at index');
        $subTitle          = trans('firefly.import_data_index');
        $subTitleIcon      = 'fa-home';
        $importFileTypes   = [];
        $defaultImportType = config('firefly.default_import_format');

        foreach (array_keys(config('firefly.import_formats')) as $type) {
            $importFileTypes[$type] = trans('firefly.import_file_type_' . $type);
        }

        return view('import.index', compact('subTitle', 'subTitleIcon', 'importFileTypes', 'defaultImportType'));
    }

    /**
     * @param ImportJob $job
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function json(ImportJob $job)
    {
        $result     = [
            'showPercentage' => false,
            'started'        => false,
            'finished'       => false,
            'running'        => false,
            'errors'         => $job->extended_status['errors'],
            'percentage'     => 0,
            'steps'          => $job->extended_status['total_steps'],
            'stepsDone'      => $job->extended_status['steps_done'],
            'statusText'     => trans('firefly.import_status_' . $job->status),
            'finishedText'   => '',
        ];
        $percentage = 0;
        if ($job->extended_status['total_steps'] !== 0) {
            $percentage = round(($job->extended_status['steps_done'] / $job->extended_status['total_steps']) * 100, 0);
        }
        if ($job->status === 'import_complete') {
            $tagId = $job->extended_status['importTag'];
            /** @var TagRepositoryInterface $repository */
            $repository             = app(TagRepositoryInterface::class);
            $tag                    = $repository->find($tagId);
            $result['finished']     = true;
            $result['finishedText'] = trans('firefly.import_finished_link', ['link' => route('tags.show', [$tag->id]), 'tag' => $tag->tag]);
        }

        if ($job->status === 'import_running') {
            $result['started']        = true;
            $result['running']        = true;
            $result['showPercentage'] = true;
            $result['percentage']     = $percentage;
        }

        return Response::json($result);
    }

    /**
     * Step 4. Save the configuration.
     *
     * @param Request                      $request
     * @param ImportJobRepositoryInterface $repository
     * @param ImportJob                    $job
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postConfigure(Request $request, ImportJobRepositoryInterface $repository, ImportJob $job)
    {
        Log::debug('Now in postConfigure()', ['job' => $job->key]);
        if (!$this->jobInCorrectStep($job, 'process')) {
            return $this->redirectToCorrectStep($job);
        }
        Log::debug('Continue postConfigure()', ['job' => $job->key]);

        // actual code
        $importer = $this->makeImporter($job);
        $data     = $request->all();
        $files    = $request->files;
        $importer->saveImportConfiguration($data, $files);

        // update job:
        $repository->updateStatus($job, 'import_configuration_saved');

        // return redirect to settings.
        // this could loop until the user is done.
        return redirect(route('import.settings', [$job->key]));
    }

    /**
     * This step 6. Depending on the importer, this will process the
     * settings given and store them.
     *
     * @param Request   $request
     * @param ImportJob $job
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws FireflyException
     */
    public function postSettings(Request $request, ImportJob $job)
    {
        Log::debug('Now in postSettings()', ['job' => $job->key]);
        if (!$this->jobInCorrectStep($job, 'store-settings')) {
            return $this->redirectToCorrectStep($job);
        }
        $importer = $this->makeImporter($job);
        $importer->storeSettings($request);

        // return redirect to settings (for more settings perhaps)
        return redirect(route('import.settings', [$job->key]));
    }

    /**
     * Step 5. Depending on the importer, this will show the user settings to
     * fill in.
     *
     * @param ImportJob $job
     *
     * @return View
     * @throws FireflyException
     */
    public function settings(ImportJobRepositoryInterface $repository, ImportJob $job)
    {
        Log::debug('Now in settings()', ['job' => $job->key]);
        if (!$this->jobInCorrectStep($job, 'settings')) {
            return $this->redirectToCorrectStep($job);
        }
        Log::debug('Continue in settings()');
        $importer     = $this->makeImporter($job);
        $subTitle     = trans('firefly.settings_for_import');
        $subTitleIcon = 'fa-wrench';

        // now show settings screen to user.
        if ($importer->requireUserSettings()) {
            Log::debug('Job requires user config.');
            $data = $importer->getDataForSettings();
            $view = $importer->getViewForSettings();

            return view($view, compact('data', 'job', 'subTitle', 'subTitleIcon'));
        }
        Log::debug('Job does NOT require user config.');

        $repository->updateStatus($job, 'settings_complete');

        // if no more settings, save job and continue to process thing.
        return redirect(route('import.complete', [$job->key]));

        // ask the importer for the requested action.
        // for example pick columns or map data.
        // depends of course on the data in the job.
    }

    /**
     * @param ImportProcedureInterface $importProcedure
     * @param ImportJob                $job
     */
    public function start(ImportProcedureInterface $importProcedure, ImportJob $job)
    {
        set_time_limit(0);
        if ($job->status == 'settings_complete') {
            $importProcedure->runImport($job);
        }
    }

    /**
     * This is the last step before the import starts.
     *
     * @param ImportJob $job
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|View
     */
    public function status(ImportJob $job)
    { //
        Log::debug('Now in status()', ['job' => $job->key]);
        if (!$this->jobInCorrectStep($job, 'status')) {
            return $this->redirectToCorrectStep($job);
        }
        $subTitle     = trans('firefly.import_status');
        $subTitleIcon = 'fa-star';

        return view('import.status', compact('job', 'subTitle', 'subTitleIcon'));
    }


    /**
     * This is step 2. It creates an Import Job. Stores the import.
     *
     * @param ImportUploadRequest          $request
     * @param ImportJobRepositoryInterface $repository
     * @param UserRepositoryInterface      $userRepository
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function upload(ImportUploadRequest $request, ImportJobRepositoryInterface $repository, UserRepositoryInterface $userRepository)
    {
        Log::debug('Now in upload()');
        // create import job:
        $type = $request->get('import_file_type');
        $job  = $repository->create($type);
        Log::debug('Created new job', ['key' => $job->key, 'id' => $job->id]);

        /** @var UploadedFile $upload */
        $upload           = $request->files->get('import_file');
        $newName          = $job->key . '.upload';
        $uploaded         = new SplFileObject($upload->getRealPath());
        $content          = $uploaded->fread($uploaded->getSize());
        $contentEncrypted = Crypt::encrypt($content);
        $disk             = Storage::disk('upload');

        // user is demo user, replace upload with prepared file.
        if ($userRepository->hasRole(auth()->user(), 'demo')) {
            $stubsDisk        = Storage::disk('stubs');
            $content          = $stubsDisk->get('demo-import.csv');
            $contentEncrypted = Crypt::encrypt($content);
            $disk->put($newName, $contentEncrypted);
            Log::debug('Replaced upload with demo file.');

            // also set up prepared configuration.
            $configuration = json_decode($stubsDisk->get('demo-configuration.json'), true);
            $repository->setConfiguration($job, $configuration);
            Log::debug('Set configuration for demo user', $configuration);

            // also flash info
            Session::flash('info', trans('demo.import-configure-security'));
        }
        if (!$userRepository->hasRole(auth()->user(), 'demo')) {
            // user is not demo, process original upload:
            $disk->put($newName, $contentEncrypted);
            Log::debug('Uploaded file', ['name' => $upload->getClientOriginalName(), 'size' => $upload->getSize(), 'mime' => $upload->getClientMimeType()]);
        }

        // store configuration file's content into the job's configuration thing. Otherwise, leave it empty.
        // demo user's configuration upload is ignored completely.
        if ($request->files->has('configuration_file') && !auth()->user()->hasRole('demo')) {
            /** @var UploadedFile $configFile */
            $configFile = $request->files->get('configuration_file');
            Log::debug(
                'Uploaded configuration file',
                ['name' => $configFile->getClientOriginalName(), 'size' => $configFile->getSize(), 'mime' => $configFile->getClientMimeType()]
            );

            $configFileObject = new SplFileObject($configFile->getRealPath());
            $configRaw        = $configFileObject->fread($configFileObject->getSize());
            $configuration    = json_decode($configRaw, true);

            // @codeCoverageIgnoreStart
            if (!is_null($configuration) && is_array($configuration)) {
                Log::debug('Found configuration', $configuration);
                $repository->setConfiguration($job, $configuration);
            }
            // @codeCoverageIgnoreEnd
        }

        return redirect(route('import.configure', [$job->key]));
    }

    /**
     * @param ImportJob $job
     * @param string    $method
     *
     * @return bool
     */
    
    /**
     * @not-used-doc
     */
    
    
    private function jobInCorrectStep(ImportJob $job, string $method): bool
    {
        Log::debug('Now in jobInCorrectStep()', ['job' => $job->key, 'method' => $method]);
        switch ($method) {
            case 'configure':
            case 'process':
                return $job->status === 'import_status_never_started';
            case 'settings':
            case 'store-settings':
                Log::debug(sprintf('Job %d with key %s has status %s', $job->id, $job->key, $job->status));

                return $job->status === 'import_configuration_saved';
            case 'finished':
                return $job->status === 'import_complete';
            case 'complete':
                return $job->status === 'settings_complete';
            case 'status':
                return ($job->status === 'settings_complete') || ($job->status === 'import_running');
        }

        return false; // @codeCoverageIgnore

    }

    /**
     * @param ImportJob $job
     *
     * @return SetupInterface
     * @throws FireflyException
     */
    private function makeImporter(ImportJob $job): SetupInterface
    {
        // create proper importer (depends on job)
        $type = strtolower($job->file_type);

        // validate type:
        $validTypes = array_keys(config('firefly.import_formats'));


        if (in_array($type, $validTypes)) {
            /** @var SetupInterface $importer */
            $importer = app('FireflyIII\Import\Setup\\' . ucfirst($type) . 'Setup');
            $importer->setJob($job);

            return $importer;
        }
        throw new FireflyException(sprintf('"%s" is not a valid file type', $type)); // @codeCoverageIgnore

    }

    /**
     * @param ImportJob $job
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws FireflyException
     */
    private function redirectToCorrectStep(ImportJob $job)
    {
        Log::debug('Now in redirectToCorrectStep()', ['job' => $job->key]);
        switch ($job->status) {
            case 'import_status_never_started':
                Log::debug('Will redirect to configure()');

                return redirect(route('import.configure', [$job->key]));
            case 'import_configuration_saved':
                Log::debug('Will redirect to settings()');

                return redirect(route('import.settings', [$job->key]));
            case 'settings_complete':
                Log::debug('Will redirect to complete()');

                return redirect(route('import.complete', [$job->key]));
            case 'import_complete':
                Log::debug('Will redirect to finished()');

                return redirect(route('import.finished', [$job->key]));
        }

        throw new FireflyException('Cannot redirect for job state ' . $job->status); // @codeCoverageIgnore

    }
}
