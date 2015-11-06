#pragma once

#include "Constraint.h"

std::vector<float> optimize(const std::vector<Constraint>& constraints, const std::vector<std::vector<float>>& returns, std::function<float(const std::vector<float>&)> returnsFunc, bool maximize, std::ostream& log);
