<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        if($request->get('user_id') !== null) {
            $response = $this->repository->getUsersJobs($request->get('user_id'));
        }
        elseif($request->__authenticatedUser->user_type == env('ADMIN_ROLE_ID') || $request->__authenticatedUser->user_type == env('SUPERADMIN_ROLE_ID'))
        {
            $response = $this->repository->getAll($request);
        } else {
            $response = collect();
        }

        return response($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        return response($this->repository->with('translatorJobRel.user')->findorfail($id));
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $data = $request->all();

        return response($this->repository->store($request->__authenticatedUser, $data));

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->all();
        $cuser = $request->__authenticatedUser;
        $response = $this->repository->updateJob($id, array_except($data, ['_token', 'submit']), $cuser);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        return response($this->repository->storeJobEmail($request->all()));
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        return $request->get('user_id') != null ?
            response($this->repository->getUsersJobsHistory($request->get('user_id'), $request)) : null;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        return response($this->repository->acceptJob($data, $user));
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        return response($this->repository->cancelJobAjax($data, $user));
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();
        return response($this->repository->endJob($data));

    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();
        return response($this->repository->customerNotCall($data));
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $user = $request->__authenticatedUser;
        return response($this->repository->getPotentialJobs($user));
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        $distance = isset($data['distance']) && $data['distance'] != "" ? $data['distance'] : '';
        $time = isset($data['time']) && $data['time'] != "" ? $data['time'] : '';
        $jobid = isset($data['jobid']) && $data['jobid'] != "" ? $data['jobid'] : '';
        $session = isset($data['session_time']) && $data['session_time'] != "" ? $data['session_time'] : '';
        $flagged = $data['flagged'] == 'true' ? 'yes' : 'no';

        if ($data['flagged'] == 'true' && $data['admincomment'] == '') {
            return "Please, add comment";
        }

        $manually_handled = $data['manually_handled'] == 'true' ? 'yes' : 'no';
        $by_admin = $data['by_admin'] == 'true' ? 'yes' : 'no';
        $admincomment = isset($data['admincomment']) && $data['admincomment'] != "" ? $data['admincomment'] : 'no';

        if ($time || $distance) {

            Distance::where('job_id', '=', $jobid)->update(array('distance' => $distance, 'time' => $time));
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {

            Job::where('id', '=', $jobid)->update(array('admin_comments' => $admincomment, 'flagged' => $flagged, 'session_time' => $session, 'manually_handled' => $manually_handled, 'by_admin' => $by_admin));

        }

        return response('Record updated!');
    }

    public function reopen(Request $request)
    {
        $data = $request->all();
        return response($this->repository->reopen($data));
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
//        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

}
