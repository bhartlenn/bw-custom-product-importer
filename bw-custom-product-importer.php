<?php
/**
 * Plugin Name: Green Grass Custom Product Importer
 * Description: Custom Product Importer for Woocommerce simple and variable products
 * Version: 1.1.5
 * Author: Ben HartLenn
 * Author URI: https://bountifulweb.com
 * License: GPL2
 * Text Domain: bw_cpi
 * 
 */

// Add menu item and admin page
function bw_cpi_plugin_menu() {

	add_submenu_page(
		"edit.php?post_type=product",
	   	"Green Grass Custom Product Importer", 
		"Green Grass Custom Product Importer", 
	   	"manage_options", 
		"custom-product-importer",
		"bw_cpi_plugin_page"
	);

}
add_action("admin_menu", "bw_cpi_plugin_menu");

// load plugin stylesheet
function bw_cpi_plugin_css($hook) {
	if( $hook == "product_page_custom-product-importer" ) {
		$plugin_url = plugin_dir_url( __FILE__ );
    	wp_enqueue_style( 'bw_cpi-style', $plugin_url . 'css/bw_cpi-style.css' );  
	}
}
add_action( 'admin_enqueue_scripts', 'bw_cpi_plugin_css' );

function bw_cpi_plugin_page() {
?>
<div id="bw_cpi-form-container">
	<h1>Green Grass Custom Product Importer</h1>
<!-- Form -->
<form method='post' action='<?= $_SERVER['REQUEST_URI']; ?>' enctype='multipart/form-data'>
  <label>Select CSV file to import... <input type="file" name="import_file" ></label> 
  <input type="submit" name="bw_cpi_import" value="Import CSV">
</form>
</div>
<?php

	// Import CSV from form post request
	if(isset($_POST['bw_cpi_import'])){

		// Get the File extension
		$extension = pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION);

		// If file extension is 'csv', and file name is not empty
		if(!empty($_FILES['import_file']['name']) && $extension == 'csv') {
			
			// Open file in read mode
			$csvFile = fopen($_FILES['import_file']['tmp_name'], 'r');
									
			$rowNum = 0;
			$bw_cpi_products_array = [];
			
			// Read file and loop through each row which will be one variable product, or if it's the same it will be a variation product
		    while( ($csvData = fgetcsv($csvFile)) !== FALSE ){
				
				// The below conditional should break the row while loop to ignore blank lines, and go to the next line. Oddly though the php fgetcsv function doesn't seem to be recognizing blank lines and returning NULL for them as per manual https://www.php.net/manual/en/function.fgetcsv.php, also tried the ini set option that a commenter pointed out might be necessary for mac users to recognize the end of lines properly, nothing changed... might have to figure out how to delete those empty arrays and elements from the array of products.
				// Could loop through arrays and remove empty elements so that the $csvData array isn't full of empty elements, i.e. is not empty itself
				if (array() === $csvData) { 
					continue;
                }
				
				// Map row data to array and encode to utf8
				$csvRow = array_map("utf8_encode", $csvData);
				
				// Store number of columns in the current row, should be 8 columns with latest format having sizes and categories in one column each
				$colLen = count($csvRow);
				
				// If csv row array is not empty, and it's not the first row, which should be set to being the header row...
				if(!empty($csvRow) && $rowNum > 0) {
					
					// If product name is not in array, make new set of product data, else if it is in the array, then the size, color, and stock data needs to be added for creating a product variation
					$bw_cpi_product_name = trim( $csvRow[0] );
					$ggcpui_product_cats = explode(",", $csvRow[5]);
					
					if( !array_key_exists( $bw_cpi_product_name, $bw_cpi_products_array ) ) {
						
						$bw_cpi_products_array[ $bw_cpi_product_name ] = [
							'description' => $csvRow[1], // add column 1 data as description string
							'colors' => [ trim($csvRow[2]) ], // set column 2 data as first/only value in colors array
							'stocks' => [ trim($csvRow[3]) ], // set column 3 data as first/only value in stocks array
							'sizes' => [ trim($csvRow[4]) ], // set column 4 data as first/only value in sizes array
							'categories' => $ggcpui_product_cats, // implode comma separated string of category values into an array. e.g. "Pets, Pets > Accessories"
							'image' => trim($csvRow[6]), // add column 6 data as image filename string
							'prices' => [ trim($csvRow[7]) ], // add column 7 data as first/only value in prices array
						];
					}
					// product is in array already, so add values to sizes, colors, and stocks arrays
					else if( array_key_exists( $bw_cpi_product_name, $bw_cpi_products_array ) ) {
						// Note that these three child/sub arrays will always remain the same length, and we will loop through them later to create product variations
						$bw_cpi_products_array[ $bw_cpi_product_name ]['colors'][] = trim($csvRow[2]); // append column 2 data to colors array
						$bw_cpi_products_array[ $bw_cpi_product_name ]['stocks'][] = trim($csvRow[3]); // append column 3 data to stocks array
						$bw_cpi_products_array[ $bw_cpi_product_name ]['sizes'][] = trim($csvRow[4]); // append column 4 data to sizes array
						$bw_cpi_products_array[ $bw_cpi_product_name ]['prices'][] = trim($csvRow[7]); // append column 7 data to prices array
					}
								
				} // End if $csvRow is not empty
				
				// Increment row number counter variable
				$rowNum++;
				
			} // end while loop	through csv rows, or end of the CSV file processing
			
/***********************************************************************************************************************************************/					
					
			/*
			* Now that we have the needed CSV data collected, as it could be collected given the csv format, the csv data in $bw_cpi_products needs to be reorganized and reformatted for creating variable products, and the variations of each variable product
			*/

			foreach($bw_cpi_products_array as $variable_product_name => $product_details) {
				
				$product_description = $product_details["description"]; // string
				$product_colors = $product_details["colors"]; // array
				$product_stocks = $product_details["stocks"]; // array
				$product_sizes = $product_details["sizes"]; // array
				$product_cats = $product_details["categories"]; // array
				$product_image = $product_details['image']; // string
				$product_prices = $product_details['prices']; // string
				
				// Create variable/parent product information array
				$variable_product_data = [
					'title' => $variable_product_name,
					'description' => $product_description,
					'categories' => $product_cats,
					'sizes' => $product_sizes, 
					'colors' => $product_colors,
					'image' => $product_image,
					//'price' => $product_price,
				];
								
				// initiate new array for collecting formatted information for variation products
				$variation_products_data = [];
				
				echo "<div class='product'>";

				echo "<h3>Name: " . $variable_product_name . "</h3>";
				
				echo "<p><b>Description:</b> " . $product_description . "</p>";
				
				echo "<ul class='product-variations'>";
				
				// Loop through each products sub arrays of data i.e. Sizes, Colors, Stocks, one line/row equals data for one product variation
				for($v=0; $v<count($product_colors); $v++) { // note that colors will always be same length as stocks and colors arrays, use any of them for limiter
					
					// Store formatted variation name e.g. "Fleece Jacket - M, Blue"
					// This could also be a description of the product variation supplied by the clients csv file
					$variation_name = $variable_product_name;
					if( !empty( $product_sizes[$v] ) ) {
						$variation_name .= " - " . $product_sizes[$v];
					}
							
					// If variation has a color
					if( !empty( $product_colors[$v] ) ) {
						// If there's already a size as well, append a comma and whitespace first
						if( !empty( $product_sizes[$v] ) ) {
							$variation_name .= ", " . $product_colors[$v];
						}
						// else just append the color name
						else {
							$variation_name .= " - " . $product_colors[$v];
						}
						
					}
					
					// Assemble the array of variation product data used to create variation products later
					$variation_products_data[$variation_name] = [
						'size' => $product_sizes[$v],
						'color' => $product_colors[$v],
						//'stock' => $product_stocks[$v], // see just below
						'price' => $product_prices[$v],
					];
					
					//extra check on stock levels to make blank stock level be 500 and ensure product is always in stock basically
					if( !empty($product_stocks[$v]) ) {
						$variation_products_data[$variation_name]['stock'] = $product_stocks[$v];
					}
					else {
						$variation_products_data[$variation_name]['stock'] = 500;
					}
					
					echo "<li>Variation: " . $variation_name . " - Stock: " . $variation_products_data[$variation_name]['stock'] . "</li>";
					
								
				}// End for loop through each products sub array 

				echo "</ul>";
				
				echo "</div>";
				
				
				$variable_product_id = bw_cpi_create_wc_variable_product($variable_product_data);
				
				// If saving the variable/parent product returns a product id instead of nothing...
				if( !empty( $variable_product_id ) ) {
					// ...then create the variations of the variable product above
					bw_cpi_create_wc_product_variations($variable_product_id, $variation_products_data);
				}
							
			}// End foreach loop through each product data element in array
			
				
		}// End check if file submitted is csv
		
	} // End check if html form was submitted
	
} // End function bw_cpi_plugin_page

// Some primer reading material for the code below that creates the Woocommerce variable and variation products with their attributes
// https://woocommerce.github.io/code-reference/classes/WC-Product-Variable.html
// https://woocommerce.github.io/code-reference/classes/WC-Product-Variation.html
// https://woocommerce.github.io/code-reference/classes/WC-Product-Attribute.html

function bw_cpi_product_cat_breeder($bw_cpi_term="", $bw_cpi_parent_id=0) {
	if( !empty($bw_cpi_term) ) {
		$bw_cpi_new_term = wp_insert_term(
			trim($bw_cpi_term), // term
			'product_cat', // taxonomy
			[
				'slug' => sanitize_title_with_dashes($bw_cpi_term),
				'parent' => $bw_cpi_parent_id, // default 0 means top level category, otherwise set a parent id for this new term
			]
		);
		if( !is_wp_error($bw_cpi_new_term) ) {
			return $bw_cpi_new_term['term_id'];
		}
	}
	
}

// Function that creates the parent/variable products and sets up the attributes to be used in later variations
function bw_cpi_create_wc_variable_product( $data = null ) {
	
	// Create new instance of woocommerce variable product class and set the details
	// Add some conditional checks on $data field before using $product->set_xxx functions to increase performance? e.g. if it has $data['title'] value in array, then set the product instances title.
	$product = new WC_Product_Variable();
	$product->set_name( sanitize_text_field( $data['title'] ) );
	$product->set_status('publish');
	$product->set_catalog_visibility('visible');
	
	// Saving a basic product so that the unique ID that gets generated for it can be used to set a unique SKU number below
	$post_id = $product->save();
	
	if( !empty($post_id) && function_exists( 'wc_get_product' ) ) {
		
		$product = wc_get_product($post_id);
		$product->set_description( sanitize_textarea_field( $data['description'] ) );
		$product->set_tax_status( 'none' );
	    $product->set_sku( 'wg-' . $post_id ); // Generate a unique SKU from product id (i.e. 'wg-123') 
	    //$product->set_regular_price( $data['price'] ); // Be sure to use the correct decimal price
		
		// If client supplies the filename in csv file row for a particular product, and the image is loaded into Wordpress's media library, then we will add the image as the main product image
		// Could feasibly take a comma separated string of image filenames and automatically have a product image gallery show up thanks to the storefront theme.
		$image_hyphenated = str_replace(" ", "-", $data['image']);
		$upload_dir = wp_upload_dir();
		$image_url = $upload_dir['url'] . "/" . $image_hyphenated;
		$product_image_id = attachment_url_to_postid( $image_url );
		if($product_image_id !== 0) {
			$product->set_image_id($product_image_id);
		}
		echo $image_url;
		// Initiate $atts array for collecting the size and color attributes of a variable product
		$atts = [];
		
		// **** BECAUSE ARRAY OF SIZES IS SOMETIMES FULL OF EMPTY ELEMENTS IT STILL SETS UP THE ATTRIBUTE WHEN IT SHOULD NOT ***
		$bw_cpi_sizes = array_filter($data['sizes']);
		
		// If there are any $data['sizes'] then setup WC size attribute for product 
		if( !empty($bw_cpi_sizes) && count( $bw_cpi_sizes ) > 0  ) {
			// This extra loop and conditional check is to ensure that duplicate size values are not added for variable products specifically
			// Build new array of sizes without duplicates
			$variable_product_sizes = [];
			foreach($bw_cpi_sizes as $size) {
				// If size is not in array already, then add it
				if( !in_array($size, $variable_product_sizes) ) {
					$variable_product_sizes[] = $size;
				}
			}

			// Create new instance of product attribute for Size
			$attribute_size = new WC_Product_Attribute();
			// Set attribute name
			$attribute_size->set_name('Size');
			// Use array of non duplicated size values to set variable product size attribute options
			$attribute_size->set_options($variable_product_sizes);
			// Set attribute to be visible
			$attribute_size->set_visible(1);		
			// We are going to use variable product attribute in order to generate variations
			$attribute_size->set_variation(1);
			// add attribute size to $atts array
			$atts[] = $attribute_size;
		}
				
				
		$bw_cpi_colors = array_filter($data['colors']);
		// If there are any $data['colors'] setup WC size attribute for product
		if( !empty($bw_cpi_colors) && count($bw_cpi_colors) > 0 ) {
			//This extra loop and conditional check is to ensure that duplicate values are not added for variable products specifically
			// Build new array of colors without duplicates
			$variable_product_colors = [];
			foreach($bw_cpi_colors as $color) {
				// If color is not in array already, then add it 
				if( !in_array($color, $variable_product_colors) ) {
					$variable_product_colors[] = $color;
				}
			}

			// Create new instance of product attribute for Color
			$attribute_color = new WC_Product_Attribute();
			// Set attribute name
			$attribute_color->set_name('Color');
			// Use array of non duplicated color values to set variable product size attribute options
			$attribute_color->set_options($variable_product_colors);
			// Set attribute to be visible
			$attribute_color->set_visible(1);
			// We are going to use attribute in order to generate variations
			$attribute_color->set_variation(1);
			// add attribute color to $atts array
			$atts[] = $attribute_color;
		}
		
		
		// If $atts is not empty, then set product attributes with array potentially containing Size and Color attributes data
		if( !empty( $atts ) ) {
			$product->set_attributes($atts);
		}
		
		// Save/update the WooCommerce product, and return the post id of the product if saved.
		$product_id = $product->save(); // which is used in the function below to create variations of this variable/parent product
		
		// set categories for product down here, so I can use wp_set_object_terms
		if( !empty( $data["categories"] ) ) {
			$product_cats = [];
			foreach($data["categories"] as $product_cat) {
				
				// If exploding the category string by the ">" character returns more than one array element then create the first element as a parent category, then the second element as the child category, and add both elements to categories array, else just add categories to array
				$exploded_cats = explode( ">", $product_cat );
				
				if( count( $exploded_cats ) > 1 ) {					
					// second element will always be child category
					$product_cats[trim($exploded_cats[0])] = trim($exploded_cats[1]);
				}
				else {
					// just a single category term, so add it to our array as a key with an empty value
					$product_cats[trim($product_cat)] = ""; // this makes $child_cat below empty
				}
				
			} // end foreach on $data['categories'] array

			/* example of $product_cats
			* $product_cats = [ 
			*    'parent_cat1' =>,
			*    'parent_cat2' => 'child_cat1',
			*    'parent_cat3' =>,
			* ];
			*/
						
			// if we did collect some categories, which is very likely, but just in case...			
			if( !empty( $product_cats ) ) {
								
				foreach( $product_cats as $parent_cat => $child_cat ) {
					// initialize variables
					$parent_cat_exists = "";
					$parent_cat_id = "";
					$parent_cat_term = "";
					$child_cat_exists = "";
					$child_cat_id = "";
					$child_cat_term = "";
						
					if( !empty($parent_cat) ) {
						
						// arguments for this query, and the way this plugin dynamically creates product category terms will ensure that only one parent term with same name is ever created at top level, so store first result found.
						$parent_cat_terms = get_terms([
						'name' => trim($parent_cat), 
						'taxonomy' => 'product_cat',
						'parent' => 0,
						]);

						// if $parent_cat_terms array is not empty, it likely contains a term object, so store its id
						if( !empty($parent_cat_terms) ) {
							$parent_cat_term = $parent_cat_terms[0];
							$parent_cat_id = $parent_cat_term->term_id;
						}
						// else get_term did not find the current product cat in the db, so need to create the current parent category on the fly(Just saying, but a fly flew past me and landed right in front of me as I wrote that...)
						else {
							$parent_cat_id = bw_cpi_product_cat_breeder($parent_cat);
						}
						
						// we should have a $product_cat_id by now, so append(4th argument of wp_set_post_terms) that term to the product that was saved above
						wp_set_post_terms( $product_id, $parent_cat_id, 'product_cat', true );
						
						// if we found a child category term entered
						if( !empty($child_cat) ) {
							// term exists, returns an array of term_id, and term taxonomy id when taxonomy is declared
							$child_cat_exists = term_exists(trim($child_cat), 'product_cat', $parent_cat_id); 
						
							// if child_cat exists, store it's id
							if( $child_cat_exists !== 0 && $child_cat_exists !== null ) {
								$child_cat_id = $child_cat_exists['term_id'];
							}
							// else need to create the child category on the fly, and add it under the parent(...There was no actual fly this time in case you were wondering)
							else {
								$child_cat_id = bw_cpi_product_cat_breeder($child_cat, $parent_cat_id);
							}
							
							wp_set_post_terms( $product_id, $child_cat_id, 'product_cat', true );
							
						} // end if $child_cat is not empty
						
					} // end if $parent_cat is not empty
					
				} // end foreach loop through product categories
				
			} // end if user input product categories
			
			// remove default uncategorized product category term, because that is being added by creating the product earlier on with no categories.
			wp_remove_object_terms($product_id, 'uncategorized', 'product_cat');
			
		} // end if csv had any product categories
		
		return $product_id;
	}
	
}

// function that creates product variations of a variable product from an array of variation data
function bw_cpi_create_wc_product_variations($variable_product_id = null, $variation_products_data = null) {
	
	// loop through product variation data that was generated for the variable product and create new variation products
	foreach($variation_products_data as $variation_name => $variation_data) {
		unset($variation_atts);
		$variation_product = new WC_Product_Variation();
		
		$variation_product->set_name($variation_name);
		$variation_product->set_parent_id($variable_product_id);
		$variation_product->set_description($variation_name);
		
		$variation_product->set_regular_price($variation_data['price']); // Need client to update their csv file with product pricing on every row, then price is set here for each variation
		
		$variation_product->set_catalog_visibility('visible');
		$variation_product->set_manage_stock(true);
		$variation_product->set_stock_quantity($variation_data['stock']);		
		
		// set the single size and single color for this product variation
		if( !empty( $variation_data['size'] ) ) {
			$variation_atts['size'] = $variation_data['size'];
		}
		if( !empty( $variation_data['color'] ) ) {
			$variation_atts['color'] = $variation_data['color'];
		}
		$variation_product->set_attributes($variation_atts);

		// save the variation product, silently so far...
		$variation_product->save();
	}
	
}

?>