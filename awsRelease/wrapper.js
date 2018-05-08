const portopt = require('./portopt');

exports.handler = async function(arg){
	return portopt.buildEfficientFrontier(arg);
}
