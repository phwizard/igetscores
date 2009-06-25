//
//  XMLParser.m
//  TPS
//
//  Created by Andrew Kopanev on 3/23/09.
//  Copyright 2009 Injoit.com. All rights reserved.
//

#import "XMLParser.h"

@implementation XMLParser

@synthesize scoresArray;
@synthesize scoresCount;
@synthesize answer;

- (void) dealloc {
	[scoresArray release];
	[answer release];
	[super dealloc];
}

- (void)parseXMLwithData:(NSData*)data parseError:(NSError **)error {	
	//NSLog(@"[XMLParser::parseXMLwithData] starting...");
    NSXMLParser *parser = [[NSXMLParser alloc] initWithData: data];

    // Set self as the delegate of the parser so that it will receive the parser delegate methods callbacks.
    [parser setDelegate: self];
    // Depending on the XML document you're parsing, you may want to enable these features of NSXMLParser.
    [parser setShouldProcessNamespaces: NO];
    [parser setShouldReportNamespacePrefixes: NO];
    [parser setShouldResolveExternalEntities: NO];
    
    [parser parse];
    
	 NSError *parseError = [parser parserError];
	 if (parseError && error) {
		 *error = parseError;
	 }
	 
	//NSLog(@"[XMLParser::parseXMLwithData] did end...");
}

- (void)parseXMLFileAtURL:(NSString *)URL parseError:(NSError **)error {	
	//NSLog(@"[XMLParser::parseXMLFileAtURL] start parsing with url: %@", URL);
    NSXMLParser *parser = [[NSXMLParser alloc] initWithContentsOfURL:
						   [NSURL URLWithString: URL] 
						   
						   ];
	
	//if (parser) NSLog(@"parser created...");

    // Set self as the delegate of the parser so that it will receive the parser delegate methods callbacks.
    [parser setDelegate: self];
    // Depending on the XML document you're parsing, you may want to enable these features of NSXMLParser.
    [parser setShouldProcessNamespaces: NO];
    [parser setShouldReportNamespacePrefixes: NO];
    [parser setShouldResolveExternalEntities: NO];
    
    [parser parse];
    
	 NSError *parseError = [parser parserError];
	 if (parseError && error) {
		 *error = parseError;
	 }
	 
    [parser release];
}

- (void)parser:(NSXMLParser *)parser foundCharacters:(NSString *)string {
    if (!currentStringValue) {
        // currentStringValue is an NSMutableString instance variable
        currentStringValue = [[NSMutableString alloc] initWithCapacity: 50];
    }
    [currentStringValue appendString: string];
}

- (void)parser:(NSXMLParser *)parser didStartElement:(NSString *)elementName 
  namespaceURI:(NSString *)namespaceURI qualifiedName:(NSString *)qName 
	attributes:(NSDictionary *)attributeDict {
	if ([elementName isEqualToString: @"game_scores"]) {
		if (!scoresArray) scoresArray = [[NSMutableArray alloc] init];
		if (!answer) answer = [[NSMutableDictionary alloc] init];
		[answer addEntriesFromDictionary: attributeDict];
		[answer setObject: elementName forKey: @"elementName"];
		[answer setObject: scoresArray forKey: @"scores"];
		return;
	}
	
	if ([elementName isEqualToString: @"score"]) {
		if (!currentScore) currentScore = [[NSMutableDictionary alloc] init];
		
		return;
	}	
}

- (void)parser:(NSXMLParser *)parser didEndElement:(NSString *)elementName namespaceURI:(NSString *)namespaceURI qualifiedName:(NSString *)qName {
	//NSLog(@"[NSXMLParser::didEnd] elementName: %@", elementName);
	if (currentScore) {
		NSString *csv = currentStringValue ? currentStringValue : @"";
		if ([@"name" isEqualToString: elementName]) {
			[currentScore setObject: [csv stringByReplacingPercentEscapesUsingEncoding: NSUTF8StringEncoding] forKey: @"name"];
		}

		if ([@"email" isEqualToString: elementName]) {
			[currentScore setObject: csv forKey: @"email"];
		}
	
		if ([@"value" isEqualToString: elementName]) {
			[currentScore setObject: csv forKey: @"value"];
		}
	
		if ([@"datetime" isEqualToString: elementName]) {
			[currentScore setObject: csv forKey: @"datetime"];
		}
	}
	
	if (scoresArray && currentScore && [elementName isEqualToString: @"score"]) {
		[scoresArray addObject: currentScore];
		[currentScore release];
		currentScore = nil;
	}
	
	if (currentStringValue) {
		[currentStringValue release];
		currentStringValue = nil;
	}
}


@end