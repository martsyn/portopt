const portopt = require('./portopt');

exports.handler = async function(arg) {
    return { result: portopt.buildEfficientFrontier(arg) };
};
