<?php

class HomeController extends BaseController {

	/*
	|--------------------------------------------------------------------------
	| Default Home Controller
	|--------------------------------------------------------------------------
	|
	| You may wish to use controllers instead of, or in addition to, Closure
	| based routes. That's great! Here is an example controller method to
	| get you started. To route to this controller, just add the route:
	|
	|	Route::get('/', 'HomeController@showWelcome');
	|
	*/

	public function showWelcome()
	{
		return View::make('hello');
	}
	public function getDownload()
	{
	$file= "ReportOut.xlsx";

    $headers = array(
              'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

    return Response::download($file, 'Rudel.xlsx', $headers);
	}
	public function exportarToPdf($id_request)
	{
		$m = new MongoClient();
		$db = $m->SenditForm;
		$collwf = $db->works_filter;
		$docRepor =$collwf->find();
		foreach ($docRepor as $k) {
			//var_dump($k['Subwork']);
		}
		//var_dump($docRepor);
		$seg = iterator_to_array($docRepor,false);
		echo $seg[5]['Subwork'];
	}


}
