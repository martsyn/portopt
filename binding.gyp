{
  "targets": [
    {
      "target_name": "portopt",
      "sources": [ "<!@(ls -1 *.cpp)" ],
	  'cflags!': [ '-fno-exceptions' ],
	  'cflags_cc!': [ '-fno-exceptions' ]
    }
  ]
}
