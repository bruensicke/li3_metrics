# li3_metrics

Lithium library for sending statistical data to [librato metrics](https://metrics.librato.com/). Have a look at their [documentation](http://dev.librato.com/v1/get/metrics).

## Installation

Add a submodule to your li3 libraries:

	git submodule add git@github.com:bruensicke/li3_metrics.git libraries/li3_metrics

and activate it in you app (config/bootstrap/libraries.php), of course:

	Libraries::add('li3_metrics');

## Usage

### Preparation

	Metrics::$username = 'd1rk@gmx.de';
	Metrics::$token = '4077254dbb55d78414a9de3b97e4e2807f5addb8c746c3a6f6edaf1cb164940a';

### Sending data

In order to send data to Metrics, you need to build an array with all gauges/counters you want to submit

	Metrics::post(array(
		'gauges' => array(
			'api.requests' => array(
				'value' => 2,
			),
			'api.requests.internal' => array(
				'value' => 3,
			),
		),
	));

You can also use a shortcut, but that works only for one gauge at a time, so try to avoid that, if you want to send more than one data-metric

	Metrics::gauge('api.requests', 1, $hostname);

### Retrieving a list of metrics

You can easily retrieve a list of all metrics you have with `get()`

	// returns all metrics
	$metrics = Metrics::get();

You can also filter your results by giving a substring to search for in name, or passing in a tag-name

	// returns only metrics, that have 'request' in their name
	$metrics = Metrics::get('request');

or

	// returns all metrics containing 'request' in their name, that carry a tag 'api'
	$metrics = Metrics::get('request', array('api'));

## Credits

* [li3](http://www.lithify.me)
* [librato metrics](https://metrics.librato.com/)


