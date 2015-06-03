#pragma once

std::vector<float> optimize(std::vector<std::vector<float>> returns, std::function<float(const std::vector<float>&)> returnsFunc, bool maximize, std::ostream& log);
