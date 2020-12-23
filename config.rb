require 'compass/import-once/activate'
# Require any add 
# Set this to the root of your project when deployed:
http_path = "/"
css_dir = "public/css"
sass_dir = "sass"
images_dir = "public/img"
#javascripts_dir = "public/js"



# environment: can be :development, or :production
#environment = :development
environment = :production

# You can select your preferred output style here (can be overridden via the command line):
# :nested, :expanded, :compact, or :compressed
output_style = (environment == :production) ? :compressed : :expanded

# To enable relative paths to assets via compass helper functions. Uncomment:
# relative_assets = true

# To disable debugging comments that display the original location of your selectors. Uncomment:
line_comments = (environment == :production) ? false : true

# If you prefer the indented syntax, you might want to regenerate this
# project again passing --syntax sass, or you can uncomment this:
# preferred_syntax = :sass
# and then run:
# sass-convert -R --from scss --to sass sass scss && rm -rf sass && mv scss sass
