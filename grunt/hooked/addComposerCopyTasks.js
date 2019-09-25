
const addComposerCopyTask = require('../addComposerCopyTask');

const addComposerCopyTasks = grunt => {

	[
		'a5hleyrich/wp-background-processing',
		'mustangostang/spyc',
	].map( name => addComposerCopyTask( grunt, { name } ) );

}

module.exports = addComposerCopyTasks;
