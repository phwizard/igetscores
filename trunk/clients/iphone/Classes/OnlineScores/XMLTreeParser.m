//
//  XMLTreeParser.m
//  daypack
//
//  Created by John Stump on 2/15/09.
//  Copyright 2009 John Stump. All rights reserved.
//

#import <Foundation/NSXMLParser.h>

#import "XMLTreeParser.h"
#import "XMLTreeNode.h"


@implementation XMLTreeParser

@synthesize root, stack;

- (XMLTreeParser*) init
{
	if((self = [super init])) {
		// set up the stack
		stack = [[NSMutableArray alloc] init];
		
		// set up the root node
		root = [[XMLTreeNode alloc] init];
		root.parent = nil;
		root.text = nil;
		root.attributes = nil;
		root.name = nil;
		root.children = [[NSMutableDictionary alloc] init];
		
		// put the root on the stack
		[stack addObject:root];
		[root release];
	}
	
	return self;
}

- (void) dealloc
{
	self.stack = nil;
	self.root = nil;
	
	[super dealloc];
}

- (XMLTreeNode*) parse:(NSData*) data
{
	NSXMLParser *parser  = [[NSXMLParser alloc] initWithData: data];
	
	[parser setDelegate:self];
	
	if([parser parse]) {
		NSLog(@"Done parse pages");
	}
	else {
		NSLog(@"Parse pages failed");
		self.root = nil;
	}
	
	[parser release];
	
	// return the root node
	return root;
}

- (void)parser:(NSXMLParser *)parser 
	didStartElement:(NSString *)elementName 
	namespaceURI:(NSString *)namespaceURI 
	qualifiedName:(NSString *) qualifiedName 
	attributes:(NSDictionary *)attributeDict 
{
	// create a new node
	XMLTreeNode* node = [[XMLTreeNode alloc] init];
	
	// set our parent
	node.parent = [stack lastObject];
	
	// add node to our parent
	// see if there is already a child with that element name already
	NSMutableArray* childList = [[node.parent children] objectForKey:elementName];
	if(childList == nil) {
		// create a new entry
		childList = [[NSMutableArray alloc] initWithCapacity:5];
		[[node.parent children] setObject:childList forKey:elementName];
		
		// stick the node on the child list
		[childList addObject:node];

		[childList release];
	}
	else {
		// stick the node on the child list
		[childList addObject:node];
	}
	
	// set our element name
	node.name = elementName;
	
	// set our attributes
	node.attributes = attributeDict;
	
	// get the cradle ready fo some children
	//node.children = [[NSMutableArray alloc] init];
	node.children = [[NSMutableDictionary alloc] init];
	// this will retain twice, so release one of the reference counts
	[node.children release];
	
	//NSLog(@"retain count of children property should be 1, it is %d", [node.children retainCount]);
	
	// put this new node on top of the stack
	[stack addObject:node];
	
	[node release];
}

- (void)parser:(NSXMLParser *)parser 
	didEndElement:(NSString *)elementName 
	namespaceURI:(NSString *)namespaceURI 
	qualifiedName:(NSString *)qName 
{
	// pop the node off the stack
	[stack removeLastObject];
}

- (void)parser:(NSXMLParser *)parser foundCharacters:(NSString *)string 
{
	// need to set the text value of the node currently on top of the stack
	XMLTreeNode* node = [stack lastObject];
	
	// this can be called multiple times for the same element, so append to whatever might already be there
	if(!node.text) {
		node.text = [NSMutableString stringWithCapacity:50];
	}
	
	[node.text appendString:string];
}

- (void) parser:(NSXMLParser*) parser foundIgnorableWhitespace:(NSString *)whitespaceString
{
	//TODO do we need to capture these, or make it configurable?
}

- (void)parser:(NSXMLParser *)parser parseErrorOccurred:(NSError *)parseError
{
	//TODO not much we can do but inform the user
}

@end
