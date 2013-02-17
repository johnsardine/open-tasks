// Dependencies: jQuery 1.8.3+
var opentasks = {

	settings: {
		base_url: '',
		timeout: 3000,
	},

	api: {
		tasks: 'index.php/api/tasks/',
	},

	add: function (parameters)
	{
		return this.update(parameters);
	},

	update: function (parameters)
	{

		// If is array, will be converted to object
		parameters = this.toObj(parameters);

		return jQuery.ajax(
		{
			url: this.api.tasks,
			type: 'POST',
			data: parameters
		});
	},

	delete: function (parameters)
	{
		throw new Error('This method is yet to be written');
	},

	get: function (parameters)
	{
		// If is array, will be converted to object
		parameters = this.toObj(parameters);

		return jQuery.ajax(
		{
			url: this.api.tasks,
			type: 'GET',
			data: parameters
		});
	},

	toObj: function (array)
	{
		if (array.constructor == Object) return array;
		var rv = {};
		for (var i = 0; i < array.length; ++i)
		if (array[i] !== undefined) rv[i] = array[i];
		return rv;
	}

},
ot = opentasks;
