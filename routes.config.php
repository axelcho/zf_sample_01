<?php
//I have deleted all 
return array(
	'search' => array(
		'type' => 'Literal',
		'options' => array(
			'route' => '/search',
			'defaults' => array(
				'__NAMESPACE__' => 'Smu\Controller',
				'controller' => 'Search',
				'action' => 'index'
			),
		),
		'may_terminate' => true,
		'child_routes' => array(
			'shows' => array(
				'type' => 'Segment',
				'options' => array(
					'route' => '/shows',
					'defaults' => array(
						'action' => 'shows'
					)
				)
			),
			'experience' => array(
				'type' => 'Segment',
				'options' => array(
					'route' => '/experience',
					'defaults' => array(
						'action' => 'experience'
					)
				)
			),
			'last-name' => array(
				'type' => 'Segment',
				'options' => array(
					'route' => '/last-name',
					'defaults' => array(
						'action' => 'last-name'
					)
				)
			),
			'premium' => array(
				'type' => 'Segment',
				'options' => array(
					'route' => '/premium',
					'defaults' => array(
						'action' => 'premium'
					)
				)
			),
			'universal' => array(
				'type' => 'Segment',
				'options' => array(
					'route' => '/universal',
					'defaults' => array(
						'action' => 'universal'
					)
				)
			),
		)
	),
	
);

?>