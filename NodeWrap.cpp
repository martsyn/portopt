#include "stdafx.h"

#include <node_api.h>
#include "Optimize.h"

using namespace std;

napi_value buildEfficientFrontierNode(napi_env env, napi_callback_info args) {
	//napi_status status;
	//status = napi_create_string_utf8(env, "what is up my dudes?", NAPI_AUTO_LENGTH, &greeting);

	cout << "starting" << endl;

	auto chk = [=](napi_status s)
	{
		if (s != napi_ok) {
			const napi_extended_error_info* err;
			napi_get_last_error_info(env, &err);
			throw runtime_error(err->error_message);
		}
	};
	const auto wrapFloat = [=](const float x)
	{
		napi_value v; 
		chk(napi_create_double(env, x, &v)); 
		return v;
	};
	const auto setProp = [=](napi_value obj, const char *name, napi_value value)
	{
		chk(napi_set_named_property(env, obj, name, value));
	};
	const auto getProp = [=](napi_value obj, const char *name)
	{
		napi_value v; 
		chk(napi_get_named_property(env, obj, name, &v));
		return v;
	};
	const auto tryGetProp = [=](napi_value arr, const char *name)
	{
		napi_value v;
		return napi_get_named_property(env, arr, name, &v) == napi_ok
			? v
			: nullptr;
	};
	const auto arrLen = [=](napi_value arr)
	{
		uint32_t len;
		chk(napi_get_array_length(env, arr, &len));
		return len;
	};
	const auto getElem = [=](napi_value arr, uint32_t i)
	{
		napi_value v; 
		chk(napi_get_element(env, arr, i, &v));
		return v;
	};
	const auto getString = [=](napi_value val)
	{
		napi_valuetype type;
		chk(napi_typeof(env, val, &type));

		stringstream res;
		switch (type)
		{
		case napi_string:
			char buf[1000];
			size_t length;
			chk(napi_get_value_string_utf8(env, val, buf, sizeof(buf), &length));
			res.write(buf, length);
			break;
		case napi_number:
			int64_t val64;
			chk(napi_get_value_int64(env, val, &val64));
			res << val64;
			break;
		default:
			res<< "unexpected type " << type;
		}
		return res.str();
	};
	const auto getFloat = [=](napi_value val, float def = numeric_limits<float>::quiet_NaN())
	{
		if (!val) return def;

		napi_valuetype type;
		chk(napi_typeof(env, val, &type));

		switch (type)
		{
		case napi_undefined:
			return def;
		case napi_number:
			double res;
			chk(napi_get_value_double(env, val, &res));
			return static_cast<float>(res);
		case napi_string:
			return stof(getString(val));
		default:
			stringstream err;
			err << "Expected float, got type=" << type;
			throw runtime_error(err.str());
		}
	};
	const auto getInt = [=](napi_value val, int def = 0)
	{
		if (!val) return def;

		napi_valuetype type;
		chk(napi_typeof(env, val, &type));

		switch (type) {
		case napi_undefined:
			return def;
		case napi_number:
			int res;
			chk(napi_get_value_int32(env, val, &res));
			return res;
		default:
			stringstream err;
			err << "Expected int, got type=" << type;
			throw runtime_error(err.str());
		}
	};
	const auto getDouble = [=](napi_value val)
	{
		double res;
		chk(napi_get_value_double(env, val, &res));
		return res;
	};
	const auto getBool = [=](napi_value val, bool def = false)
	{
		if (!val) return def;
		napi_valuetype type;
		chk(napi_typeof(env, val, &type));

		switch (type)
		{
		case napi_undefined:
			return def;
		case napi_boolean:
			bool res;
			chk(napi_get_value_bool(env, val, &res));
			return res;
		default:
			stringstream err;
			err << "Expected bool, got type=" << type;
			throw runtime_error(err.str());
		}
	};
	const auto renderVal = [=](napi_value val)
	{
		napi_valuetype type;
		chk(napi_typeof(env, val, &type));
		stringstream res;
		switch (type)
		{
		case napi_undefined:
			res << "(undefined)";
			break;
		case napi_null:
			res << "(null)";
			break;
		case napi_boolean:
			res << "(boolean) " << (getBool(val) ? "true" : "false");
			break;
		case napi_number:
			res << "(number) " << getDouble(val);
			break;
		case napi_string:
			res << "(string) '" << getString(val) << "'";
			break;
		case napi_object:
			res << "(object)";
			break;
		default:
			res << "(unknown type =" << type << ")";
		}
		return res.str();
	};

	try {

		cout << "lambdas done" << endl;
		size_t argc = 1;
		napi_value arg;
		napi_get_cb_info(env, args, &argc, &arg, nullptr, nullptr);

		vector<string> ids;
		cout << "argc=" << argc << endl;
		auto nodeSeries = getProp(arg, "series");
		napi_value nodeIds;
		napi_get_property_names(env, nodeSeries, &nodeIds);
		auto seriesCount = arrLen(nodeIds);

		cout << "seriesCount=" << seriesCount << endl;

		size_t retCount = 0;
		vector<vector<float>> returns;
		vector<Constraint> constraints;
		for (size_t i = 0; i < seriesCount; ++i)
		{
			auto id = getString(getElem(nodeIds, i));
			cout << " id=" << id << endl;
			ids.push_back(id);
			const auto nodeSeriesElem = getProp(nodeSeries, id.c_str());

			// napi_valuetype type;
			// napi_typeof(env, nodeSeriesElem, &type);
			// cout << "type=" << type << endl;

			const auto nodeReturns = getProp(nodeSeriesElem, "returns");
			if (!retCount)
			{
				retCount = arrLen(nodeReturns);
				if (retCount < 2)
					napi_throw_error(env, nullptr, "count of 'returns' < 2");
			}
			else if (arrLen(nodeReturns) != retCount)
				napi_throw_error(env, nullptr, "mismatching size of 'returns'");
			vector<float> rets;
			for (size_t j = 0; j < retCount; ++j)
				rets.push_back(getFloat(getElem(nodeReturns, j)));
			returns.push_back(rets);
			auto required = getBool(tryGetProp(nodeSeriesElem, "required"), false);
			auto min = getFloat(tryGetProp(nodeSeriesElem, "min"), 0.0f);
			auto max = getFloat(tryGetProp(nodeSeriesElem, "max"), 1.0f);
			constraints.push_back(Constraint(required, min, max));
		}
		if (returns.size() < 2)
			napi_throw_error(env, nullptr, "count of 'series' < 2");

		cout << "got " << returns.size() << " series" << endl;

		const auto normalizationCount = getInt(tryGetProp(arg, "normalizationCount"), 12);

		cout << "normalizationCount=" << normalizationCount << endl;

		ReturnStats factors = ReturnStats::zero;
		auto factorsNode = tryGetProp(arg, "factors");
		if (factorsNode)
		{
			// // TODO: finish populating factors
			// map<string, double> factorsMap = GetVal(paramMap, "factors", map<string, double>());
			// if (factorsMap.size() > 0)
			// 	statsFromMap(factorsMap, factors);
			// else
		}
		else
		{
			factors.meanReturn = 1.0f;
		}

		cout << "done converting params" << endl;
		auto points = buildEfficientFrontier(constraints, returns, normalizationCount, factors);

		napi_value nodePoints;
		napi_create_array(env, &nodePoints);
		for (size_t p = 0; p < points.size(); ++p) {
			const auto& point = points[p];
			napi_value nodePoint;

			napi_create_object(env, &nodePoint);

			setProp(nodePoint, "annualizedReturn", wrapFloat(point.stats.meanReturn));
			setProp(nodePoint, "annualizedReturn", wrapFloat(point.stats.meanReturn));

			setProp(nodePoint, "annualizedVol", wrapFloat(point.stats.stdDeviation));
			setProp(nodePoint, "negativeDeviation", wrapFloat(point.stats.negativeDeviation));
			setProp(nodePoint, "positiveDeviation", wrapFloat(point.stats.positiveDeviation));
			setProp(nodePoint, "slopeDeviation", wrapFloat(point.stats.slopeDeviation));
			setProp(nodePoint, "worstDrawdown", wrapFloat(point.stats.worstDrawdown));
			setProp(nodePoint, "skewness", wrapFloat(point.stats.skewness));
			setProp(nodePoint, "kurtosis", wrapFloat(point.stats.kurtosis));

			napi_value nodeWeights;
			napi_create_object(env, &nodeWeights);
			for (size_t i = 0; i < point.weights.size(); ++i)
				setProp(nodeWeights, ids[i].c_str(), wrapFloat(point.weights[i]));
			setProp(nodePoint, "weights", nodeWeights);

			napi_set_element(env, nodePoints, p, nodePoint);
		}
		return nodePoints;
	}
	catch (const exception &err)
	{
		napi_throw_error(env, nullptr, err.what());
	}
	return nullptr;
}

napi_value init(napi_env env, napi_value exports) {
	napi_status status;
	napi_value fn;
	
	cout << "init" << endl;

	status = napi_create_function(env, nullptr, 0, buildEfficientFrontierNode, nullptr, &fn);
	if (status != napi_ok) return nullptr;

	cout << "created function" << endl;

	status = napi_set_named_property(env, exports, "buildEfficientFrontier", fn);
	if (status != napi_ok) return nullptr;
	
	cout << "registered function" << endl;

	return exports;
}

NAPI_MODULE(NODE_GYP_MODULE_NAME, init)
