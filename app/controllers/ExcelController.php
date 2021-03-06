<?php

class ExcelController extends \BaseController {

	public function turn_dates($date){
		$date = new DateTime($date);
		$date->setTimezone(new DateTimeZone('America/Santiago'));
		return $date->format('j F, Y, g:i a');
	}
	/**
	 * easy image resize function
	 * @param  $file - file name to resize
	 * @param  $string - The image data, as a string
	 * @param  $width - new image width
	 * @param  $height - new image height
	 * @param  $proportional - keep image proportional, default is no
	 * @param  $output - name of the new file (include path if needed)
	 * @param  $delete_original - if true the original image will be deleted
	 * @param  $use_linux_commands - if set to true will use "rm" to delete the image, if false will use PHP unlink
	 * @param  $quality - enter 1-100 (100 is best quality) default is 100
	 * @return boolean|resource
	 */
	  function smart_resize_image($file,
	                              $string             = null,
	                              $width              = 0,
	                              $height             = 0,
	                              $proportional       = false,
	                              $output             = 'file',
	                              $delete_original    = true,
	                              $use_linux_commands = false,
	  							  $quality = 100
	  		 ) {

	    if ( $height <= 0 && $width <= 0 ) return false;
	    if ( $file === null && $string === null ) return false;

	    # Setting defaults and meta
	    $info                         = $file !== null ? getimagesize($file) : getimagesizefromstring($string);
	    $image                        = '';
	    $final_width                  = 0;
	    $final_height                 = 0;
	    list($width_old, $height_old) = $info;
		$cropHeight = $cropWidth = 0;

	    # Calculating proportionality
	    if ($proportional) {
	      if      ($width  == 0)  $factor = $height/$height_old;
	      elseif  ($height == 0)  $factor = $width/$width_old;
	      else                    $factor = min( $width / $width_old, $height / $height_old );

	      $final_width  = round( $width_old * $factor );
	      $final_height = round( $height_old * $factor );
	    }
	    else {
	      $final_width = ( $width <= 0 ) ? $width_old : $width;
	      $final_height = ( $height <= 0 ) ? $height_old : $height;
		  $widthX = $width_old / $width;
		  $heightX = $height_old / $height;

		  $x = min($widthX, $heightX);
		  $cropWidth = ($width_old - $width * $x) / 2;
		  $cropHeight = ($height_old - $height * $x) / 2;
	    }

	    # Loading image to memory according to type
	    switch ( $info[2] ) {
	      case IMAGETYPE_JPEG:  $file !== null ? $image = imagecreatefromjpeg($file) : $image = imagecreatefromstring($string);  break;
	      case IMAGETYPE_GIF:   $file !== null ? $image = imagecreatefromgif($file)  : $image = imagecreatefromstring($string);  break;
	      case IMAGETYPE_PNG:   $file !== null ? $image = imagecreatefrompng($file)  : $image = imagecreatefromstring($string);  break;
	      default: return false;
	    }


	    # This is the resizing/resampling/transparency-preserving magic
	    $image_resized = imagecreatetruecolor( $final_width, $final_height );
	    if ( ($info[2] == IMAGETYPE_GIF) || ($info[2] == IMAGETYPE_PNG) ) {
	      $transparency = imagecolortransparent($image);
	      $palletsize = imagecolorstotal($image);

	      if ($transparency >= 0 && $transparency < $palletsize) {
	        $transparent_color  = imagecolorsforindex($image, $transparency);
	        $transparency       = imagecolorallocate($image_resized, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
	        imagefill($image_resized, 0, 0, $transparency);
	        imagecolortransparent($image_resized, $transparency);
	      }
	      elseif ($info[2] == IMAGETYPE_PNG) {
	        imagealphablending($image_resized, false);
	        $color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);
	        imagefill($image_resized, 0, 0, $color);
	        imagesavealpha($image_resized, true);
	      }
	    }
	    imagecopyresampled($image_resized, $image, 0, 0, $cropWidth, $cropHeight, $final_width, $final_height, $width_old - 2 * $cropWidth, $height_old - 2 * $cropHeight);


	    # Taking care of original, if needed
	    if ( $delete_original ) {
	      if ( $use_linux_commands ) exec('rm '.$file);
	      else @unlink($file);
	    }

	    # Preparing a method of providing result
	    switch ( strtolower($output) ) {
	      case 'browser':
	        $mime = image_type_to_mime_type($info[2]);
	        header("Content-type: $mime");
	        $output = NULL;
	      break;
	      case 'file':
	        $output = $file;
	      break;
	      case 'return':
	        return $image_resized;
	      break;
	      default:
	      break;
	    }

	    # Writing image according to type to the output destination and image quality
	    switch ( $info[2] ) {
	      case IMAGETYPE_GIF:   imagegif($image_resized, $output);    break;
	      case IMAGETYPE_JPEG:  imagejpeg($image_resized, $output, $quality);   break;
	      case IMAGETYPE_PNG:
	        $quality = 9 - (int)((0.9*$quality)/10.0);
	        imagepng($image_resized, $output, $quality);
	        break;
	      default: return false;
	    }

	    return true;
	}

	function resize_image($file, $w, $h, $crop=FALSE) {
	    list($width, $height) = getimagesize($file);
	    $r = $width / $height;
	    if ($crop) {
	        if ($width > $height) {
	            $width = ceil($width-($width*abs($r-$w/$h)));
	        } else {
	            $height = ceil($height-($height*abs($r-$w/$h)));
	        }
	        $newwidth = $w;
	        $newheight = $h;
	    } else {
	        if ($w/$h > $r) {
	            $newwidth = $h*$r;
	            $newheight = $h;
	        } else {
	            $newheight = $w/$r;
	            $newwidth = $w;
	        }
	    }
	    $src = imagecreatefromjpeg($file);
	    $dst = imagecreatetruecolor($newwidth, $newheight);
	    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

	    return $dst;
	}

	public function exportToExcel()
	{
		$m = new MongoClient();
		$db = $m->SenditForm;
		$collW = $db->Works;
		$docRepor = $collW->find();
		$docRepor = $docRepor->sort(['Dsr' => 1]);

	  $objPHPExcel = new PHPExcel();
	 	$objReader = PHPExcel_IOFactory::createReader('Excel2007');
	 	$objPHPExcel = $objReader->load("/var/www/senditlaravel42/public/reporteRudel.xlsx");
	 	$objWorksheet= $objPHPExcel->setActiveSheetIndex(0);
	 	//Datos Fijos
	 	$collf = $db->works_filter;
		$dataFix = $collf->find();
		$dataFix =iterator_to_array($dataFix,false);
		//echo $dataFix[0]['Loc'];


	 	$objPHPExcel->getActiveSheet()->SetCellValue('H9', $dataFix[0]['Loc']);
	 	$objPHPExcel->getActiveSheet()->SetCellValue('AD9', $dataFix[0]['Std']);
	 	$objPHPExcel->getActiveSheet()->SetCellValue('AD10', $dataFix[0]['Stn']);
	 	$objPHPExcel->getActiveSheet()->SetCellValue('AD11', $dataFix[0]['Itd']);
	 	$objPHPExcel->getActiveSheet()->SetCellValue('AD12', $dataFix[0]['Stn']);
	 	$objPHPExcel->getActiveSheet()->SetCellValue('H11', $dataFix[0]['Blk']);
	 	$objPHPExcel->getActiveSheet()->SetCellValue('I14', $dataFix[0]['Dsp']);
	 	$objPHPExcel->getActiveSheet()->SetCellValue('I15', $dataFix[0]['Dep']);
	 	$objPHPExcel->getActiveSheet()->SetCellValue('I16', $dataFix[0]['Hp']);

	 	$row = 21;
		foreach ($docRepor as $v) {

			$objPHPExcel->getActiveSheet()->SetCellValue('D'.(string)($row), $v["Work"]);
			$row++;
		}
		$row = 21;
		foreach ($docRepor as $v) {

			$objPHPExcel->getActiveSheet()->SetCellValue('E'.(string)($row), $v["Subwork"]);
			$row++;
		}
		$row = 21;
		foreach ($docRepor as $v) {

			$objPHPExcel->getActiveSheet()->SetCellValue('AB'.(string)($row), $this->turn_dates($v["Dsr"]));
			$row++;
		}
		$row = 21;
		foreach ($docRepor as $v) {

			$objPHPExcel->getActiveSheet()->SetCellValue('AH'.(string)($row), $this->turn_dates($v["Der"]));
			$row++;
		}
		$row = 21;
		foreach ($docRepor as $v) {

			$objPHPExcel->getActiveSheet()->SetCellValue('AN'.(string)($row), $v["Poop"]."%");
			$row++;
		}
		//					Imagenes                    //


		$photo =iterator_to_array($docRepor,false);
		switch (count($photo)) {
			case 1:
				//Primera Foto
				if ($photo[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D49', $photo[0]['Leyend']);
					$name_photo = substr($photo[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
			break;
			case 2:
				//Primera Foto
				if ($photo[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D49', $photo[0]['Leyend']);
					$name_photo = substr($photo[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Segunda Foto
				if ($photo[1]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R49', $photo[1]['Leyend']);
					$name_photo = substr($photo[1]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
			break;
			case 3:
				//Primera Foto
				if ($photo[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D49', $photo[0]['Leyend']);
					$name_photo = substr($photo[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Segunda Foto
				if ($photo[1]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R49', $photo[1]['Leyend']);
					$name_photo = substr($photo[1]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Tercera Foto
				if ($photo[2]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF49', $photo[2]['Leyend']);
					$name_photo = substr($photo[2]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
			break;
			case 4:
				//Primera Foto
				if ($photo[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D49', $photo[0]['Leyend']);
					$name_photo = substr($photo[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Segunda Foto
				if ($photo[1]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R49', $photo[1]['Leyend']);
					$name_photo = substr($photo[1]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Tercera Foto
				if ($photo[2]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF49', $photo[2]['Leyend']);
					$name_photo = substr($photo[2]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Cuarta
				if ($photo[3]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D64', $photo[3]['Leyend']);
					$name_photo = substr($photo[3]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B65');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
			break;
			case 5:
				//Primera Foto
				if ($photo[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D49', $photo[0]['Leyend']);
					$name_photo = substr($photo[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Segunda Foto
				if ($photo[1]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R49', $photo[1]['Leyend']);
					$name_photo = substr($photo[1]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Tercera Foto
				if ($photo[2]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF49', $photo[2]['Leyend']);
					$name_photo = substr($photo[2]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Cuarta
				if ($photo[3]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D64', $photo[3]['Leyend']);
					$name_photo = substr($photo[3]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B65');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Quinta
				if ($photo[4]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R64', $photo[4]['Leyend']);
					$name_photo = substr($photo[4]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P65');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
			break;
			case 6:
				//Primera Foto
				if ($photo[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D49', $photo[0]['Leyend']);
					$name_photo = substr($photo[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Segunda Foto
				if ($photo[1]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R49', $photo[1]['Leyend']);
					$name_photo = substr($photo[1]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Tercera Foto
				if ($photo[2]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF49', $photo[2]['Leyend']);
					$name_photo = substr($photo[2]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Cuarta
				if ($photo[3]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D64', $photo[3]['Leyend']);
					$name_photo = substr($photo[3]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B65');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Quinta
				if ($photo[4]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R64', $photo[4]['Leyend']);
					$name_photo = substr($photo[4]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P65');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Sexta
				if ($photo[5]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF64', $photo[5]['Leyend']);
					$name_photo = substr($photo[5]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD65');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}

			break;

			default:
			//Primera Foto
				if ($photo[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D49', $photo[0]['Leyend']);
					$name_photo = substr($photo[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Segunda Foto
				if ($photo[1]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R49', $photo[1]['Leyend']);
					$name_photo = substr($photo[1]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Tercera Foto
				if ($photo[2]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF49', $photo[2]['Leyend']);
					$name_photo = substr($photo[2]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD50');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Cuarta
				if ($photo[3]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D64', $photo[3]['Leyend']);
					$name_photo = substr($photo[3]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B65');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Quinta
				if ($photo[4]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R64', $photo[4]['Leyend']);
					$name_photo = substr($photo[4]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P65');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
				//Sexta
				if ($photo[5]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF64', $photo[5]['Leyend']);
					$name_photo = substr($photo[5]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD65');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}
			break;
		}



		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
		header('Content-Disposition: attachment; filename="ReportOut.xlsx"');
		header("Cache-Control: max-age=0");
		$objWriter->save("ReportOut.xlsx");
		$objWriter->save("php://output");
	}

	public function exportarToExcel($requestId)
	{

		$m = new MongoClient();
		$db = $m->SenditForm;
		$collwf = $db->works_filter;
		$docRepor =$collwf->find(["RequestId" => $requestId]);

		$seg = iterator_to_array($docRepor,false);

		switch (count($seg)) {

			case 1:
				$objPHPExcel = new PHPExcel();
				$objReader = PHPExcel_IOFactory::createReader('Excel2007');
				$objPHPExcel = $objReader->load("/var/www/senditlaravel42/public/reporteRudel.xlsx");
				$objWorksheet= $objPHPExcel->setActiveSheetIndex(0);

				//Exporto Datos Fijos
				$objPHPExcel->getActiveSheet()->SetCellValue('H9', $seg[0]['Loc']);
				$objPHPExcel->getActiveSheet()->SetCellValue('H11', $seg[0]['Blk']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I14', $seg[0]['Dsp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I15', $seg[0]['Dep']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I16', $seg[0]['Hp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD9', $seg[0]['Std']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD10', $seg[0]['Stn']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD11', $seg[0]['Itd']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD12', $seg[0]['Itn']);
				//Primer Trabajo
				$objPHPExcel->getActiveSheet()->SetCellValue('D20', $seg[0]['Work']);
				$objPHPExcel->getActiveSheet()->SetCellValue('E21', $seg[0]['Subwork']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AB21', $this->turn_dates($seg[0]['Dsr']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AH21',$this->turn_dates($seg[0]['Der']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AN21', $seg[0]['Poop']);
				$objPHPExcel->getActiveSheet()->setShowGridlines(true);
				//Itero los otro Trabajos
				$row = 22;
				for ($i=1; $i <count($seg) ; $i++) {
					$objPHPExcel->getActiveSheet()->SetCellValue('E'.$row, $seg[$i]['Subwork']);
					$objPHPExcel->getActiveSheet()->SetCellValue('AB'.$row, $this->turn_dates($seg[$i]['Dsr']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AH'.$row,$this->turn_dates($seg[$i]['Der']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AN'.$row, $seg[$i]['Poop']);
					$row++;
				}
				//Observaciones
				$objPHPExcel->getActiveSheet()->SetCellValue('D45', $seg[0]['Subwork'].": ".$seg[0]['Obs']);

				//					Imagenes                    //

				//Primera Foto


				if ($seg[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D52', $seg[0]['Leyend']);
					$name_photo = substr($seg[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header('Content-Disposition: attachment; filename="ReportOut.xlsx"');
				header("Cache-Control: max-age=0");
				$objWriter->save("ReportOut.xlsx");
				$objWriter->save("php://output");

			break;

			case 2:
				$objPHPExcel = new PHPExcel();
				$objReader = PHPExcel_IOFactory::createReader('Excel2007');
				$objPHPExcel = $objReader->load("/var/www/senditlaravel42/public/reporteRudel.xlsx");
				$objWorksheet= $objPHPExcel->setActiveSheetIndex(0);

				//Exporto Datos Fijos
				$objPHPExcel->getActiveSheet()->SetCellValue('H9', $seg[0]['Loc']);
				$objPHPExcel->getActiveSheet()->SetCellValue('H11', $seg[0]['Blk']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I14', $seg[0]['Dsp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I15', $seg[0]['Dep']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I16', $seg[0]['Hp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD9', $seg[0]['Std']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD10', $seg[0]['Stn']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD11', $seg[0]['Itd']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD12', $seg[0]['Itn']);
				//Primer Trabajo
				$objPHPExcel->getActiveSheet()->SetCellValue('D20', $seg[0]['Work']);
				$objPHPExcel->getActiveSheet()->SetCellValue('E21', $seg[0]['Subwork']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AB21', $this->turn_dates($seg[0]['Dsr']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AH21',$this->turn_dates($seg[0]['Der']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AN21', $seg[0]['Poop']);
				$objPHPExcel->getActiveSheet()->setShowGridlines(true);
				//Itero los otro Trabajos
				$row = 22;
				for ($i=1; $i <count($seg) ; $i++) {
					$objPHPExcel->getActiveSheet()->SetCellValue('E'.$row, $seg[$i]['Subwork']);
					$objPHPExcel->getActiveSheet()->SetCellValue('AB'.$row, $this->turn_dates($seg[$i]['Dsr']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AH'.$row,$this->turn_dates($seg[$i]['Der']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AN'.$row, $seg[$i]['Poop']);
					$row++;
				}
				//Observaciones

				$objPHPExcel->getActiveSheet()->SetCellValue('D45', $seg[0]['Subwork'].": ".$seg[0]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D46', $seg[1]['Subwork'].": ".$seg[1]['Obs']);

				//					Imagenes                    //

				//Primera Foto
				if ($seg[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D52', $seg[0]['Leyend']);

					$name_photo = substr($seg[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				//Segunda foto
				if ($seg[1]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R52', $seg[1]['Leyend']);
					$name_photo = substr( $seg[1]['Photo'],-22);
					$foto2 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 2');
					$objDrawing->setDescription('Trabajo 2');

					list($width, $height) = getimagesize($foto2);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header('Content-Disposition: attachment; filename="ReportOut.xlsx"');
				header("Cache-Control: max-age=0");
				$objWriter->save("ReportOut.xlsx");
				$objWriter->save("php://output");

			break;

			case 3:
				$objPHPExcel = new PHPExcel();
				$objReader = PHPExcel_IOFactory::createReader('Excel2007');
				$objPHPExcel = $objReader->load("/var/www/senditlaravel42/public/reporteRudel.xlsx");
				$objWorksheet= $objPHPExcel->setActiveSheetIndex(0);

				//Exporto Datos Fijos
				$objPHPExcel->getActiveSheet()->SetCellValue('H9', $seg[0]['Loc']);
				$objPHPExcel->getActiveSheet()->SetCellValue('H11', $seg[0]['Blk']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I14', $seg[0]['Dsp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I15', $seg[0]['Dep']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I16', $seg[0]['Hp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD9', $seg[0]['Std']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD10', $seg[0]['Stn']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD11', $seg[0]['Itd']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD12', $seg[0]['Itn']);
				//Primer Trabajo
				$objPHPExcel->getActiveSheet()->SetCellValue('D20', $seg[0]['Work']);
				$objPHPExcel->getActiveSheet()->SetCellValue('E21', $seg[0]['Subwork']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AB21', $this->turn_dates($seg[0]['Dsr']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AH21',$this->turn_dates($seg[0]['Der']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AN21', $seg[0]['Poop']);
				$objPHPExcel->getActiveSheet()->setShowGridlines(true);
				//Itero los otro Trabajos
				$row = 22;
				for ($i=1; $i <count($seg) ; $i++) {
					$objPHPExcel->getActiveSheet()->SetCellValue('E'.$row, $seg[$i]['Subwork']);
					$objPHPExcel->getActiveSheet()->SetCellValue('AB'.$row, $this->turn_dates($seg[$i]['Dsr']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AH'.$row,$this->turn_dates($seg[$i]['Der']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AN'.$row, $seg[$i]['Poop']);
					$row++;
				}
				//Observaciones

				$objPHPExcel->getActiveSheet()->SetCellValue('D45', $seg[0]['Subwork'].": ".$seg[0]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D46', $seg[1]['Subwork'].": ".$seg[1]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D47', $seg[2]['Subwork'].": ".$seg[2]['Obs']);
				//					Imagenes                    //

				//Primera Foto
				if ($seg[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D52', $seg[0]['Leyend']);

					$name_photo = substr($seg[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				//Segunda foto
				if ($seg[1]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R52', $seg[1]['Leyend']);

					$name_photo = substr( $seg[1]['Photo'],-22);
					$foto2 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 2');
					$objDrawing->setDescription('Trabajo 2');

					list($width, $height) = getimagesize($foto2);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				//Tercera foto
				if ($seg[2]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF52', $seg[2]['Leyend']);

					$name_photo = substr( $seg[2]['Photo'],-22);
					$foto3 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 3');
					$objDrawing->setDescription('Trabajo 3');

					list($width, $height) = getimagesize($foto3);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header('Content-Disposition: attachment; filename="ReportOut.xlsx"');
				header("Cache-Control: max-age=0");
				$objWriter->save("ReportOut.xlsx");
				$objWriter->save("php://output");

			break;

			case 4:
				$objPHPExcel = new PHPExcel();
				$objReader = PHPExcel_IOFactory::createReader('Excel2007');
				$objPHPExcel = $objReader->load("/var/www/senditlaravel42/public/reporteRudel.xlsx");
				$objWorksheet= $objPHPExcel->setActiveSheetIndex(0);

				//Exporto Datos Fijos
				$objPHPExcel->getActiveSheet()->SetCellValue('H9', $seg[0]['Loc']);
				$objPHPExcel->getActiveSheet()->SetCellValue('H11', $seg[0]['Blk']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I14', $seg[0]['Dsp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I15', $seg[0]['Dep']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I16', $seg[0]['Hp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD9', $seg[0]['Std']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD10', $seg[0]['Stn']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD11', $seg[0]['Itd']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD12', $seg[0]['Itn']);
				//Primer Trabajo
				$objPHPExcel->getActiveSheet()->SetCellValue('D20', $seg[0]['Work']);
				$objPHPExcel->getActiveSheet()->SetCellValue('E21', $seg[0]['Subwork']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AB21', $this->turn_dates($seg[0]['Dsr']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AH21',$this->turn_dates($seg[0]['Der']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AN21', $seg[0]['Poop']);
				$objPHPExcel->getActiveSheet()->setShowGridlines(true);
				//Itero los otro Trabajos
				$row = 22;
				for ($i=1; $i <count($seg) ; $i++) {
					$objPHPExcel->getActiveSheet()->SetCellValue('E'.$row, $seg[$i]['Subwork']);
					$objPHPExcel->getActiveSheet()->SetCellValue('AB'.$row, $this->turn_dates($seg[$i]['Dsr']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AH'.$row,$this->turn_dates($seg[$i]['Der']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AN'.$row, $seg[$i]['Poop']);
					$row++;
				}
				//Observaciones

				$objPHPExcel->getActiveSheet()->SetCellValue('D45', $seg[0]['Subwork'].": ".$seg[0]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D46', $seg[1]['Subwork'].": ".$seg[1]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D47', $seg[2]['Subwork'].": ".$seg[2]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D48', $seg[3]['Subwork'].": ".$seg[3]['Obs']);
				//					Imagenes                    //

				//Primera Foto
				if ($seg[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D52', $seg[0]['Leyend']);

					$name_photo = substr($seg[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				//Segunda foto
				if ($seg[1]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R52', $seg[1]['Leyend']);

					$name_photo = substr( $seg[1]['Photo'],-22);
					$foto2 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 2');
					$objDrawing->setDescription('Trabajo 2');

					list($width, $height) = getimagesize($foto2);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				//Tercera foto
				if ($seg[2]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF52', $seg[2]['Leyend']);

					$name_photo = substr( $seg[2]['Photo'],-22);
					$foto3 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 3');
					$objDrawing->setDescription('Trabajo 3');

					list($width, $height) = getimagesize($foto3);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}

				//Cuarta foto
				if ($seg[3]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D67', $seg[3]['Leyend']);

					$name_photo = substr($seg[3]['Photo'],-22);
					$foto4 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 4');
					$objDrawing->setDescription('Trabajo 4');

					list($width, $height) = getimagesize($foto4);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B68');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header('Content-Disposition: attachment; filename="ReportOut.xlsx"');
				header("Cache-Control: max-age=0");
				$objWriter->save("ReportOut.xlsx");
				$objWriter->save("php://output");

			break;

			case 5:

				$objPHPExcel = new PHPExcel();
				$objReader = PHPExcel_IOFactory::createReader('Excel2007');
				$objPHPExcel = $objReader->load("/var/www/senditlaravel42/public/reporteRudel.xlsx");
				$objWorksheet= $objPHPExcel->setActiveSheetIndex(0);

				// estilos
				$styleArray = array(
				    'font'  => array(
				        'bold'  => true,
				        'color' => array('rgb' => 'FF0000'),
				        'size'  => 35,
				        'name'  => 'Verdana'
				    ));
				$objPHPExcel->getActiveSheet()->getStyle('B50')->applyFromArray($styleArray);
				//$objPHPExcel->getDefaultStyle()->applyFromArray($styleArray);

				//Exporto Datos Fijos

				$objPHPExcel->getActiveSheet()->SetCellValue('H9', $seg[0]['Loc']);
				$objPHPExcel->getActiveSheet()->SetCellValue('H11', $seg[0]['Blk']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I14', $seg[0]['Dsp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I15', $seg[0]['Dep']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I16', $seg[0]['Hp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD9', $seg[0]['Std']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD10', $seg[0]['Stn']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD11', $seg[0]['Itd']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD12', $seg[0]['Itn']);

				//Primer Trabajo

				$objPHPExcel->getActiveSheet()->SetCellValue('D20', $seg[0]['Work']);
				$objPHPExcel->getActiveSheet()->SetCellValue('E21', $seg[0]['Subwork']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AB21', $this->turn_dates($seg[0]['Dsr']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AH21',$this->turn_dates($seg[0]['Der']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AN21', $seg[0]['Poop']);
				$objPHPExcel->getActiveSheet()->setShowGridlines(true);

				//Itero los otro Trabajos

				$row = 22;
				for ($i=1; $i <count($seg) ; $i++) {
					$objPHPExcel->getActiveSheet()->SetCellValue('E'.$row, $seg[$i]['Subwork']);
					$objPHPExcel->getActiveSheet()->SetCellValue('AB'.$row, $this->turn_dates($seg[$i]['Dsr']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AH'.$row,$this->turn_dates($seg[$i]['Der']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AN'.$row, $seg[$i]['Poop']);
					$row++;
				}
				//Observaciones

				$objPHPExcel->getActiveSheet()->SetCellValue('D45', $seg[0]['Subwork'].": ".$seg[0]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D46', $seg[1]['Subwork'].": ".$seg[1]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D47', $seg[2]['Subwork'].": ".$seg[2]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D48', $seg[3]['Subwork'].": ".$seg[3]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D49', $seg[4]['Subwork'].": ".$seg[4]['Obs']);

				//					Imagenes                    //

				//Primera Foto


				//echo $width, $height;

			    //indicate the path and name for the new resized file
			    //$foto1Resized = '/var/www/senditlaravel42/public/photos/'.$name_photo;

			   // $foto1 = $this->resize_image('/var/www/senditlaravel42/public/photos/'.$name_photo, 456, 556);
			    //$output ='/var/www/senditlaravel42/public/photos/resized/'.$name_photo;
			   // imagejpeg($foto1, $output, 100);

			    //call the function (when passing path to pic)
			    //$this->smart_resize_image($foto1, null, 800, 800, false, $foto1Resized, true, false, 100 );

			    //call the function (when passing pic as string)
			    //smart_resize_image(null , file_get_contents($file), SET_YOUR_WIDTH , SET_YOUR_HIGHT , false , $resizedFile , false , false ,100 );

				//Primera Foto
				if ($seg[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D52', $seg[0]['Leyend']);

					$name_photo = substr($seg[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				//Segunda foto
				if ($seg[1]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R52', $seg[1]['Leyend']);

					$name_photo = substr( $seg[1]['Photo'],-22);
					$foto2 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 2');
					$objDrawing->setDescription('Trabajo 2');

					list($width, $height) = getimagesize($foto2);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				//Tercera foto
				if ($seg[2]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF52', $seg[2]['Leyend']);

					$name_photo = substr( $seg[2]['Photo'],-22);
					$foto3 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 3');
					$objDrawing->setDescription('Trabajo 3');

					list($width, $height) = getimagesize($foto3);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}

				//Cuarta foto
				if ($seg[3]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D67', $seg[3]['Leyend']);

					$name_photo = substr($seg[3]['Photo'],-22);
					$foto4 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 4');
					$objDrawing->setDescription('Trabajo 4');

					list($width, $height) = getimagesize($foto4);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B68');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}

				//Quinta foto
				if ($seg[4]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R67', $seg[4]['Leyend']);

					$name_photo = substr($seg[4]['Photo'],-22);
					$foto5 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 5');
					$objDrawing->setDescription('Trabajo 5');

					list($width, $height) = getimagesize($foto5);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P68');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}



				/* Guardo y Descargo*/

				$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header('Content-Disposition: attachment; filename="ReportOut.xlsx"');
				header("Cache-Control: max-age=0");
				$objWriter->save("ReportOut.xlsx");
				$objWriter->save("php://output");

			break;

			case 6:
				$objPHPExcel = new PHPExcel();
				$objReader = PHPExcel_IOFactory::createReader('Excel2007');
				$objPHPExcel = $objReader->load("/var/www/senditlaravel42/public/reporteRudel.xlsx");
				$objWorksheet= $objPHPExcel->setActiveSheetIndex(0);

				//Exporto Datos Fijos
				$objPHPExcel->getActiveSheet()->SetCellValue('H9', $seg[0]['Loc']);
				$objPHPExcel->getActiveSheet()->SetCellValue('H11', $seg[0]['Blk']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I14', $seg[0]['Dsp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I15', $seg[0]['Dep']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I16', $seg[0]['Hp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD9', $seg[0]['Std']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD10', $seg[0]['Stn']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD11', $seg[0]['Itd']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD12', $seg[0]['Itn']);
				//Primer Trabajo
				$objPHPExcel->getActiveSheet()->SetCellValue('D20', $seg[0]['Work']);
				$objPHPExcel->getActiveSheet()->SetCellValue('E21', $seg[0]['Subwork']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AB21', $this->turn_dates($seg[0]['Dsr']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AH21',$this->turn_dates($seg[0]['Der']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AN21', $seg[0]['Poop']);
				$objPHPExcel->getActiveSheet()->setShowGridlines(true);
				//Itero los otro Trabajos
				$row = 22;
				for ($i=1; $i <count($seg) ; $i++) {
					$objPHPExcel->getActiveSheet()->SetCellValue('E'.$row, $seg[$i]['Subwork']);
					$objPHPExcel->getActiveSheet()->SetCellValue('AB'.$row, $this->turn_dates($seg[$i]['Dsr']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AH'.$row,$this->turn_dates($seg[$i]['Der']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AN'.$row, $seg[$i]['Poop']);
					$row++;
				}
				//Observaciones

				$objPHPExcel->getActiveSheet()->SetCellValue('D45', $seg[0]['Subwork'].": ".$seg[0]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D46', $seg[1]['Subwork'].": ".$seg[1]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D47', $seg[2]['Subwork'].": ".$seg[2]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D48', $seg[3]['Subwork'].": ".$seg[3]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D49', $seg[4]['Subwork'].": ".$seg[4]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D50', $seg[5]['Subwork'].": ".$seg[5]['Obs']);
				//					Imagenes                    //

				//Primera Foto
				if ($seg[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D52', $seg[0]['Leyend']);

					$name_photo = substr($seg[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					list($width, $height) = getimagesize($foto1);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				//Segunda foto
				if ($seg[1]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R52', $seg[1]['Leyend']);

					$name_photo = substr( $seg[1]['Photo'],-22);
					$foto2 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 2');
					$objDrawing->setDescription('Trabajo 2');

					list($width, $height) = getimagesize($foto2);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				//Tercera foto
				if ($seg[2]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF52', $seg[2]['Leyend']);

					$name_photo = substr( $seg[2]['Photo'],-22);
					$foto3 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 3');
					$objDrawing->setDescription('Trabajo 3');

					list($width, $height) = getimagesize($foto3);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}

				//Cuarta foto
				if ($seg[3]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D67', $seg[3]['Leyend']);

					$name_photo = substr($seg[3]['Photo'],-22);
					$foto4 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 4');
					$objDrawing->setDescription('Trabajo 4');

					list($width, $height) = getimagesize($foto4);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B68');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}

				//Quinta foto
				if ($seg[4]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R67', $seg[4]['Leyend']);

					$name_photo = substr($seg[4]['Photo'],-22);
					$foto5 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 5');
					$objDrawing->setDescription('Trabajo 5');

					list($width, $height) = getimagesize($foto5);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P68');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}

				//Sexta foto
				if ($seg[5]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF67', $seg[5]['Leyend']);
					$name_photo = substr($seg[5]['Photo'],-22);
					$foto6 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 6');
					$objDrawing->setDescription('Trabajo 6');

					list($width, $height) = getimagesize($foto6);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD68');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}



				$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header('Content-Disposition: attachment; filename="ReportOut.xlsx"');
				header("Cache-Control: max-age=0");
				$objWriter->save("ReportOut.xlsx");
				$objWriter->save("php://output");

			break;

			default:
				$objPHPExcel = new PHPExcel();
				$objReader = PHPExcel_IOFactory::createReader('Excel2007');
				$objPHPExcel = $objReader->load("/var/www/senditlaravel42/public/reporteRudel.xlsx");
				$objWorksheet= $objPHPExcel->setActiveSheetIndex(0);

				//Exporto Datos Fijos
				$objPHPExcel->getActiveSheet()->SetCellValue('H9', $seg[0]['Loc']);
				$objPHPExcel->getActiveSheet()->SetCellValue('H11', $seg[0]['Blk']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I14', $seg[0]['Dsp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I15', $seg[0]['Dep']);
				$objPHPExcel->getActiveSheet()->SetCellValue('I16', $seg[0]['Hp']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD9', $seg[0]['Std']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD10', $seg[0]['Stn']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD11', $seg[0]['Itd']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AD12', $seg[0]['Itn']);
				//Primer Trabajo
				$objPHPExcel->getActiveSheet()->SetCellValue('D20', $seg[0]['Work']);
				$objPHPExcel->getActiveSheet()->SetCellValue('E21', $seg[0]['Subwork']);
				$objPHPExcel->getActiveSheet()->SetCellValue('AB21', $this->turn_dates($seg[0]['Dsr']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AH21',$this->turn_dates($seg[0]['Der']));
				$objPHPExcel->getActiveSheet()->SetCellValue('AN21', $seg[0]['Poop']);
				$objPHPExcel->getActiveSheet()->setShowGridlines(true);
				//Itero los otro Trabajos
				$row = 22;
				for ($i=1; $i <count($seg)-1 ; $i++) {
					$objPHPExcel->getActiveSheet()->SetCellValue('E'.$row, $seg[$i]['Subwork']);
					$objPHPExcel->getActiveSheet()->SetCellValue('AB'.$row, $this->turn_dates($seg[$i]['Dsr']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AH'.$row,$this->turn_dates($seg[$i]['Der']));
					$objPHPExcel->getActiveSheet()->SetCellValue('AN'.$row, $seg[$i]['Poop']);
					$row++;
				}
				//Observaciones

				$objPHPExcel->getActiveSheet()->SetCellValue('D45', $seg[0]['Subwork'].": ".$seg[0]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D46', $seg[1]['Subwork'].": ".$seg[1]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D47', $seg[2]['Subwork'].": ".$seg[2]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D48', $seg[3]['Subwork'].": ".$seg[3]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D49', $seg[4]['Subwork'].": ".$seg[4]['Obs']);
				$objPHPExcel->getActiveSheet()->SetCellValue('D50', $seg[5]['Subwork'].": ".$seg[5]['Obs']);
				//					Imagenes                    //

				//Primera Foto
				if ($seg[0]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D52', $seg[0]['Leyend']);

					$name_photo = substr($seg[0]['Photo'],-22);
					$foto1 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 1');
					$objDrawing->setDescription('Trabajo 1');

					try {
						list($width, $height) = getimagesize($foto1);
					} catch (Exception $e) {
						echo "No ha sido posible descargar fotos";
						return Redirect::to('/dataform')
                	    ->with('mensaje_error', 'supera numero de trabajos y sin fotos');

					}

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				//Segunda foto
				if ($seg[1]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R52', $seg[1]['Leyend']);

					$name_photo = substr( $seg[1]['Photo'],-22);
					$foto2 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 2');
					$objDrawing->setDescription('Trabajo 2');

					list($width, $height) = getimagesize($foto2);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				//Tercera foto
				if ($seg[2]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF52', $seg[2]['Leyend']);

					$name_photo = substr( $seg[2]['Photo'],-22);
					$foto3 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 3');
					$objDrawing->setDescription('Trabajo 3');

					list($width, $height) = getimagesize($foto3);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD53');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}

				//Cuarta foto
				if ($seg[3]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('D67', $seg[3]['Leyend']);

					$name_photo = substr($seg[3]['Photo'],-22);
					$foto4 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 4');
					$objDrawing->setDescription('Trabajo 4');

					list($width, $height) = getimagesize($foto4);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('B68');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}

				//Quinta foto
				if ($seg[4]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('R67', $seg[4]['Leyend']);

					$name_photo = substr($seg[4]['Photo'],-22);
					$foto5 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 5');
					$objDrawing->setDescription('Trabajo 5');

					list($width, $height) = getimagesize($foto5);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('P68');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}

				//Sexta foto
				if ($seg[5]['Photo']!=null) {
					$objPHPExcel->getActiveSheet()->SetCellValue('AF67', $seg[5]['Leyend']);
					$name_photo = substr($seg[5]['Photo'],-22);
					$foto6 = '/var/www/senditlaravel42/public/photos/'.$name_photo;

					$objDrawing = new PHPExcel_Worksheet_Drawing();
					$objDrawing->setName('Foto Trabajo 6');
					$objDrawing->setDescription('Trabajo 6');

					list($width, $height) = getimagesize($foto6);

					if ($height > $width) {
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						//$objDrawing->setRotation(90);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetX(80);
						$objDrawing->setOffsetY(4);
					}else{
						$objDrawing->setPath('/var/www/senditlaravel42/public/photos/'.$name_photo);
						$objDrawing->setHeight(345);
						$objDrawing->setOffsetY(4);
						$objDrawing->setOffsetX(1);
					}
					$objDrawing->setCoordinates('AD68');
					$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
				}


				$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header('Content-Disposition: attachment; filename="ReportOut.xlsx"');
				header("Cache-Control: max-age=0");
				$objWriter->save("ReportOut.xlsx");
				$objWriter->save("php://output");
				break;
		}


	}





}//end class
