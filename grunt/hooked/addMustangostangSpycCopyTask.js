
const addComposerCopyTask = require('../addComposerCopyTask');

const addMustangostangSpycCopyTask = grunt => {

	[
		// 'jms/serializer',
		// 'jms/metadata',
		// 'jms/parser-lib',
		// 'doctrine/annotations',
		// 'doctrine/instantiator',
		// 'doctrine/lexer',
		// 'hoa/compiler',
		// 'phpcollection/phpcollection',
		// 'phpoption/phpoption',
		// 'symfony/polyfill-ctype',
		// 'symfony/yaml',



		'mustangostang/spyc',
	].map( name => addComposerCopyTask( grunt, { name } ) );

}

module.exports = addMustangostangSpycCopyTask;
