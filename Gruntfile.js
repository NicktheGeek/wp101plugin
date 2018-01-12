module.exports = function(grunt) {
	'use strict';

	grunt.initConfig({

		pkg: grunt.file.readJSON('package.json'),

		cssmin: {
			options: {
				sourceMap: true
			},
			target: {
				files: {
					'assets/admin.min.css': [
						'assets/admin.css'
					]
				}
			}
		},

		eslint: {
			options: {
				configFile: '.eslintrc'
			},
			target: [
				'assets/js/wp101.js'
			]
		},

		uglify: {
			options: {
				banner: '/*! WP101 - v<%= pkg.version %> */',
				sourceMap: true
			},
			main: {
				files: {
					'assets/js/wp101.min.js': [
						'assets/js/wp101.js'
					]
				}
			}
		},

		makepot: {
			target: {
				options: {
					domainPath: '/languages',
					exclude: [
						'\.git/*',
						'bin/*',
						'node_modules/*',
						'tests/*'
					],
					mainFile: 'wp101.php',
					potFilename: 'wp101.pot',
					potHeaders: {
						poedit: true,
						'x-poedit-keywordslist': true
					},
					type: 'wp-plugin',
					updateTimestamp: false
				}
			}
		},

		sass: {
			options: {
				outputStyle: 'compressed',
				sourceMap: true
			},
			dist: {
				files: [{
					expand: true,
					cwd: 'assets/css/scss',
					src: ['*.scss'],
					dest: 'assets/css',
					ext: '.css'
				}]
			}
		}
	});

	grunt.loadNpmTasks( 'grunt-sass' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-wp-i18n' );

	grunt.registerTask( 'build', [ 'eslint', 'i18n', 'sass', 'uglify' ] );
	grunt.registerTask( 'i18n', [ 'makepot' ] );
	grunt.registerTask( 'default', [ 'eslint', 'sass', 'uglify' ] );

	grunt.util.linefeed = '\n';
};
