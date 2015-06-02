#include <phpcpp.h>
#include <string>
#include <sstream>

using namespace Php;

Value optimize(Parameters &params)
{
	std::ostringstream res;
	res << "called with ";
	for (auto m : params)
		res << m.type() << ",";

	return res.str();
}

/**
 *  tell the compiler that the get_module is a pure C function
 */
extern "C" {
    
    PHPCPP_EXPORT void *get_module()
    {
        // static(!) Php::Extension object that should stay in memory
        // for the entire duration of the process (that's why it's static)
        static Extension extension("portopt", "1.0");
        
		extension.add("optimize", optimize, 
		{
			ByVal("returns", Type::Array),
			ByVal("optimization", Type::String),
			ByVal("benchmarks", Type::Array, false),
		});
		
        // return the extension
        return extension;
    }
}
