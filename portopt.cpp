// PortCpp.cpp : Defines the entry point for the console application.
//

#include "stdafx.h"
#include "OptimizationTargets.h"
#include "VectorMath.h"
#include "Optimize.h"
#include "onullstream.h"
#include "Constraint.h"

using namespace std;

vector<vector<float>> ReadMatrix(const char *path) {
  vector<vector<float>> rows;

  ifstream in(path);
  size_t portCount = 0;
  for (string line; getline(in, line);) {
    istringstream str(line);
    vector<float> vals;
    bool first = rows.size() == 0;
    if (!first)
      vals.reserve(portCount);
    float x;
    while (!str.eof()) {
      str >> x;
      vals.push_back(x);
    }
    if (first)
      portCount = vals.size();
    else if (vals.size() != portCount)
      throw "Mismatching column count";

    rows.push_back(vals);
  }

  return rows;
}

vector<float> ReadVector(const char *path) {
  vector<float> res;

  ifstream in(path);
  float x;
  while (in >> x)
    res.push_back(x);
  return res;
}

int main() {
  auto path = "someReturns.tsv";
  cout << "reading " << path << "\n";
  const auto rows = ReadMatrix(path);
  size_t portCount = rows[0].size();
  size_t retCount = rows.size();
  cout << "read " << portCount << " portfolios with " << retCount
       << " points each\n";
/*
  const auto benchmarkPath = "c:\\projects\\dumps\\spx.tsv";
  cout << "reading " << benchmarkPath << "\n";
  auto benchmark = ReadVector(benchmarkPath);
  cout << "read " << benchmark.size() << "\n";

  if (benchmark.size() != retCount) {
    cerr << "mismatching counts\n";
    exit(1);
  }

*/
  
  auto targets = ReturnStats::nan;
  //targets.totalReturn = 0.3f;
  targets.deviation = 0.06f/sqrt(12.0f);

  auto returnsFunc = CustomRatio(
	  OptimizationParams{
	  ReturnStats{
		  1.0f,   // totalReturn;
		  -1.0f,  // deviation;
		  0.0f,   // slopeDeviation;
		  0.0f,   // positiveDeviation;
		  0.0f,   // negativeDeviation;
		  0.0f,   // worstDrawdown;
		  {}, // benchmarkCorrelations
			  },
				  targets
      },
      {});

  // auto returnsFunc = ReturnsToStDevRatio;
  // auto returnsFunc = CorrelationToBenchmark(benchmark);
  // auto maximize = true;

  vector<Constraint> constraints = {
    //Constraint(false, 0.1f, 0.8f),
    //Constraint(false, 0.0f, 1.0f),
    //Constraint(true,  0.1f, 0.11f),
    //Constraint(true,  0.2f, 0.2f),
    //Constraint(false, 0.2f, 0.2f),
  };

  constraints.reserve(portCount);
  for (auto i = constraints.size(); i < portCount; ++i)
	  constraints.push_back(Constraint(false, 0.0f, 1.0f));

  /*
  try {
	  auto weights = optimize(constraints, rows, returnsFunc, maximize, cout);
	  vector<float> returns(retCount);
	  CalcReturns(rows, weights, returns);
	  auto res = returnsFunc(returns);
	  auto sum = 0.f;
	  for (auto w : weights)
		  sum += w;
	  cout << "result: " << res << "\nweights: " << weights << " (sum=" << sum << ")\n";

  	auto stats = GetStats(returns, {});

	cout << "totalReturn: " << stats.totalReturn << endl;
cout << "deviation: " << stats.deviation << endl;
cout << "slopeDeviation: " << stats.slopeDeviation << endl;
cout << "positiveDeviation: " << stats.positiveDeviation << endl;
cout << "negativeDeviation: " << stats.negativeDeviation << endl;
cout << "worstDrawdown: " << stats.worstDrawdown << endl;

cout << "annualized vol: " << stats.deviation*sqrt(12.0f) << endl;

	  cout << endl;
  }
  catch (const char* x) {
	  cerr << x << endl;
  }

	*/

	auto chart = buildEfficientFrontier(constraints, rows, 12);

	cout.precision(4);

	for (const auto &point : chart)
	{
		const auto & s = point.stats;
		cout << "vol=" << s.stdev*100.f << "% ret=" << s.mean*100.f << "% skew=" << s.skew << " kurt=" << s.kurt;
		cout << point.weights << endl;
	}
}
