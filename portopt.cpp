// PortCpp.cpp : Defines the entry point for the console application.
//

#include "stdafx.h"
#include "OptimizationTargets.h"
#include "Optimize.h"
#include "onullstream.h"

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

void _tmain() {
  auto path = "c:\\projects\\dumps\\gallery.tsv";
  cout << "reading " << path << "\n";
  const auto rows = ReadMatrix(path);
  size_t portCount = rows[0].size();
  size_t retCount = rows.size();
  cout << "read " << portCount << " portfolios with " << retCount
       << " points each\n";

  const auto benchmarkPath = "c:\\projects\\dumps\\spx.tsv";
  cout << "reading " << benchmarkPath << "\n";
  auto benchmark = ReadVector(benchmarkPath);
  cout << "read " << benchmark.size() << "\n";

  if (benchmark.size() != retCount) {
    cerr << "mismatching counts\n";
    exit(1);
  }

  auto returnsFunc = CustomRatio(
      {
          1.0f,   // totalReturn;
          0.0f,  // deviation;
          0.0f,   // slopeDeviation;
          0.0f,   // positiveDeviation;
          0.0f,   // negativeDeviation;
          0.0f,   // worstDrawdown;
          {-1.0f}, // benchmarkCorrelations
      },
      {benchmark});
  // auto returnsFunc = ReturnsToStDevRatio;
  // auto returnsFunc = CorrelationToBenchmark(benchmark);
  auto maximize = true;

  auto weights = optimize(rows, returnsFunc, maximize, onullstream::instance());
}
