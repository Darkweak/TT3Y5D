<?php

namespace App\Controller;

use App\Helpers\PDF;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class App
{
	const TYPE_CHOICES = ['work', 'school', 'sport', 'swim', 'validationSport'];
	const DEFAULT_BLANK_FIELD = '............';
	const DEFAULT_MARGIN = 10;
	private $environment;

	public function __construct(Environment $environment)
	{
		$this->environment = $environment;
	}

	private function render(string $template, array $options = []): Response
	{
		return new Response(
			$this->generateEnvironment(
				$template,
				array_merge(
					[
						'title' => getenv('APP_NAME'),
					],
					$options
				)
			)
		);
	}

	public function welcome(): Response
	{
		return $this->render('welcome');
	}

	public function certificates(): Response
	{
		return $this->render('certificates');
	}

	public function certificatesPDF(Request $request): Response
	{
		//\var_dump($request->request->all());
		//die();

		$disease = 'school' === $request->request->get('type') ? "<strong>{$request->request->get('disease')}</strong>" : self::DEFAULT_BLANK_FIELD;
		$sport = 'validationSport' === $request->request->get('type') ? "<strong>{$request->request->get('disease')}</strong>" : self::DEFAULT_BLANK_FIELD;

		$listChoices = [
			' présente un état de santé nécessitant un arrêt de travail de ',
			" ne pourra fréquenter l'école, le collège, le lycée, pour cause de {$disease} pendant ",
			" doit être dispensé d'éducation physique et sportive pendant ",
			' est exempté de piscine pendant ',
			"présente ce jour, une absence de signes clinique apparent contre-indiquant la pratique du sport suivant : {$sport}"
		];

		$pdf = (new PDF())
			->setContent(
				$this->generateEnvironment(
					'certificatesPDF',
					[
						'infos' => $request->request->all(),
						'listChoices' => $listChoices,
						'typeChoices' => self::TYPE_CHOICES
					]
				)
			);

		$checkboxes = [80, 101, 122, 144, 159];

		$informations = $this->generateImage(
			$request->request->get('doctorName') ?: 'DUCHAUSSOY',
			$request->request->get('doctorcity'),
			$request->request->get('postcode'),
			'N° Ordre : 015 215 632',
			true
		);

		$randomX = rand(85, 95);
		$randomY = rand(20,22);
		$randomRotate = rand(-10, 10);
		$randomXSign = rand(65, 75);
		$randomYSign = rand(35, 37);
		$randomRotateSign = rand(20, 40);
		$randomWidthSign = rand(65, 80);
		$randomHeightSign = rand(15, 30);

		$pdf->transform($randomRotate, $randomX + 50, $randomY + 15);
		$pdf->setImage($informations, $randomX, $randomY, 70, 25);
		$pdf->stopTransform();
		$pdf->transform($randomRotateSign, $randomXSign + $randomWidthSign, $randomYSign + $randomHeightSign);
		$pdf->setImage($this->generateSignature('Duchaussoy'), $randomXSign,$randomYSign,$randomWidthSign,$randomHeightSign);
		$pdf->stopTransform();

		//$pdf->setImage(PDF::BASE_RESOURCES.'cadre.png', 74,9,78,44);

		foreach ($checkboxes as $checkbox) {
			$pdf->setCheckbox($checkbox);
		}


		return $pdf->show();
	}

	private function generateEnvironment(string $template, array $options): string
	{
		return $this->environment->render(
			"Views/{$template}.html.twig",
			$options
		);
	}




	private function commonImage($image, string $name): string
	{
		header('Content-Type: image/png');

		$save = PDF::BASE_RESOURCES.'images/'.$name.'.png';
		imagepng($image, $save);

		return $save;
	}

	private function generateImage(
		string $name,
		string $place,
		string $code,
		string $phone,
		bool $replacement
	): string
	{
		$infos = [
			"Docteur DUCHAUSSOY",
			'01 MÉDECINE GÉNÉRALE',
			'51100 REIMS',
			'Tél. 06 06 06 06 06',
			'N° Ordre : 015 215 632'
		];

		if ($replacement) {
			array_splice($infos, 1, 0, 'Médecin remplaçante');
		}

		$imageSize = $this->findMinWidthAndTotalHeight($infos);

		$im = imagecreate($imageSize[0], $imageSize[1]);

		for ($i = 0; $i < count($infos); $i++) {
			$this->setText($im, ($i+1) * (16 + self::DEFAULT_MARGIN), $infos[$i], 'cantarell.regular.otf');
		}

		return $this->commonImage($im, $name);
	}

	private function findMinWidthAndTotalHeight(array $values): array
	{
		$minimal_width = 0;
		$total_height = 0;

		foreach ($values as $value) {
			$result = $this->generateText($value, 20);
			if ($minimal_width < $result) {
				$minimal_width = $result[0];
			}
			$total_height += $result[1] + self::DEFAULT_MARGIN;
		}

		return [ $minimal_width, $total_height ];
	}

	private function setText($im, int $y, $value, string $font, int $size = 16, $grey = 128): void
	{
		imagecolorallocatealpha($im, 255, 255, 255, 127);
		$grey = imagecolorallocate($im, $grey, $grey, $grey);
		$font = PDF::BASE_RESOURCES.$font;
		imagettftext($im, $size, 0, 5, $y, $grey, $font, $value);
	}

	private function generateSignature(string $text): string
	{
		$size = 40;
		$image_size = $this->generateText($text, $size, 'signerica_thin.ttf');
		$im = imagecreate($image_size[0], $image_size[1]);
		$this->setText($im, $image_size[1] - (($size + self::DEFAULT_MARGIN)/2), $text, 'signerica_thin.ttf', $size, 0);

		return $this->commonImage($im, 'signatures/'.$text);
	}

	private function generateText($text, $size, $font = 'cantarell.regular.otf'): array
	{
		$type_space = imagettfbbox($size, 0, PDF::BASE_RESOURCES.$font, $text);
		return [
			abs($type_space[4] - $type_space[0]),
			abs($type_space[5] - $type_space[1])
		];
	}
}
